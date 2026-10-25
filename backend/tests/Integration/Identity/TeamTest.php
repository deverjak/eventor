<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity;

use Eventor\Identity\Contract\Event\MemberInvited;
use Eventor\Identity\Contract\Event\MembershipChanged;
use Eventor\Identity\Contract\InvitationQuery;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Integration\Identity\Support\IdentityHttpTestCase;
use Eventor\Tests\Integration\Identity\Support\Json;

/**
 * ORG-03: invitations, acceptance (new and existing account), roles, event scope, removal.
 */
final class TeamTest extends IdentityHttpTestCase
{
    private const string EVENT_A = '0199a000-0000-7000-8000-00000000000a';
    private const string EVENT_B = '0199a000-0000-7000-8000-00000000000b';

    public function test_an_invitation_is_stored_hashed_published_and_listed(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub', 'Klub Plzeň');
        $this->login('jana@example.test');

        $body = $this->request('POST', '/api/admin/org/klub/invitations', ['email' => 'Eva@Example.test', 'role' => 'EVENT_MANAGER', 'eventScope' => [self::EVENT_A]]);

        self::assertResponseStatusCodeSame(201);
        $token = Json::str($body, 'acceptToken');
        $invitationId = Json::str($body, 'invitationId');
        self::assertStringStartsWith('inv1_'.$invitationId.'_', $token);

        $row = $this->dbRow('SELECT email, role, token_sha256, event_ids FROM identity.invitation');
        self::assertSame('eva@example.test', $row['email']);
        self::assertSame(hash('sha256', $token), $row['token_sha256']);
        self::assertStringNotContainsString($token, json_encode($row, \JSON_THROW_ON_ERROR));
        self::assertIsString($row['event_ids']);
        self::assertSame([self::EVENT_A], json_decode($row['event_ids'], true));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'invitation.created'"));

        $events = $this->published(MemberInvited::class);
        self::assertCount(1, $events);
        self::assertSame('EVENT_MANAGER', $events[0]->role);
        self::assertSame($invitationId, $events[0]->invitationId->toString());
        self::assertTrue($events[0]->organizerId->equals(OrganizerId::fromString($owner['organizerId'])));

        $list = $this->request('GET', '/api/admin/org/klub/invitations');
        $items = Json::rows($list, 'items');
        self::assertCount(1, $items);
        self::assertSame('eva@example.test', $items[0]['email']);
        self::assertSame([self::EVENT_A], $items[0]['eventScope']);

        // Communication reads the address and the accept link through the facade, never from the event.
        $delivery = $this->service(InvitationQuery::class)->forDelivery(OrganizerId::fromString($owner['organizerId']), InvitationId::fromString($invitationId));
        self::assertSame('eva@example.test', $delivery->recipientEmail);
        self::assertSame('Klub Plzeň', $delivery->organizerName);
        self::assertStringContainsString('token='.$token, $delivery->acceptUrl);
    }

    public function test_the_invitee_previews_and_accepts_with_a_new_account_and_is_signed_in(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub', 'Klub Plzeň');
        $this->login('jana@example.test');
        $token = $this->invite('klub', 'eva@example.test', 'FINANCE');
        $this->logout();

        $preview = $this->request('POST', '/api/auth/invitations/preview', ['token' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame(['organizerName' => 'Klub Plzeň', 'organizerSlug' => 'klub', 'email' => 'eva@example.test', 'role' => 'FINANCE', 'accountExists' => false], array_diff_key((array) $preview, ['expiresAt' => 1]));

        $result = $this->request('POST', '/api/auth/invitations/accept', ['token' => $token, 'password' => 'eva-password-123', 'displayName' => 'Eva']);

        self::assertResponseIsSuccessful();
        self::assertTrue(Json::at($result, 'accountCreated'));
        self::assertSame('klub', Json::at($result, 'organizerSlug'));
        self::assertSame('FINANCE', Json::at($result, 'profile', 'memberships', 0, 'role'));

        $org = $this->request('GET', '/api/admin/org/klub');
        self::assertResponseIsSuccessful();
        self::assertSame('FINANCE', Json::at($org, 'yourRole'));
        self::assertSame(2, Json::at($org, 'memberCount'));

        $granted = array_values(array_filter($this->published(MembershipChanged::class), static fn(MembershipChanged $c): bool => 'FINANCE' === $c->newRole));
        self::assertCount(1, $granted);
        self::assertNull($granted[0]->previousRole);
        self::assertTrue($granted[0]->organizerId->equals(OrganizerId::fromString($owner['organizerId'])));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'invitation.accepted'"));

        // The new member can sign in again with the chosen password.
        $this->logout();
        $this->login('eva@example.test', 'eva-password-123');
    }

    public function test_an_existing_account_signs_in_first_and_then_accepts_gaining_a_second_organizer(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->createOwner('eva@example.test', 'evin-klub');
        $this->login('jana@example.test');
        $token = $this->invite('klub', 'eva@example.test', 'CHECK_IN', [self::EVENT_A, self::EVENT_B]);
        $this->logout();

        $this->request('POST', '/api/auth/invitations/accept', ['token' => $token, 'password' => 'whatever-1234', 'displayName' => 'Eva']);
        $this->assertProblem(409, 'sign_in_required');

        $this->login('eva@example.test');
        $result = $this->request('POST', '/api/auth/invitations/accept', ['token' => $token]);

        self::assertResponseIsSuccessful();
        self::assertFalse(Json::at($result, 'accountCreated'));
        self::assertCount(2, Json::arr($result, 'profile', 'memberships'));
        self::assertEqualsCanonicalizing([self::EVENT_A, self::EVENT_B], $this->connection()->fetchFirstColumn('SELECT event_id FROM identity.membership_event_scope'));

        $me = $this->request('GET', '/api/auth/me');
        self::assertEqualsCanonicalizing(['evin-klub', 'klub'], array_column(Json::rows($me, 'memberships'), 'slug'));
        $this->request('GET', '/api/admin/org/klub');
        self::assertResponseIsSuccessful();
    }

    public function test_accepting_as_another_signed_in_account_is_403(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->createOwner('petr@example.test', 'petruv-klub');
        $this->login('jana@example.test');
        $token = $this->invite('klub', 'eva@example.test', 'FINANCE');
        $this->logout();

        $this->login('petr@example.test');
        $this->request('POST', '/api/auth/invitations/accept', ['token' => $token]);

        $this->assertProblem(403, 'invitation_email_mismatch');
    }

    public function test_an_anonymous_acceptance_needs_a_password_and_a_name(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $token = $this->invite('klub', 'eva@example.test', 'FINANCE');
        $this->logout();

        $this->request('POST', '/api/auth/invitations/accept', ['token' => $token]);
        $this->assertProblem(422, 'account_details_required');

        $this->request('POST', '/api/auth/invitations/accept', ['token' => $token, 'password' => 'short', 'displayName' => 'Eva']);
        $this->assertProblem(422, 'validation_failed');
        self::assertSame(0, $this->dbInt("SELECT count(*) FROM identity.user_account WHERE email = 'eva@example.test'"));
    }

    public function test_an_invitation_cannot_be_used_twice_nor_after_expiry_revocation_or_with_a_wrong_token(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $used = $this->invite('klub', 'a@example.test', 'FINANCE');
        $expired = $this->invite('klub', 'b@example.test', 'FINANCE');
        $revokedBody = $this->request('POST', '/api/admin/org/klub/invitations', ['email' => 'c@example.test', 'role' => 'FINANCE']);
        $this->request('DELETE', '/api/admin/org/klub/invitations/'.Json::str($revokedBody, 'invitationId'));
        self::assertResponseStatusCodeSame(204);
        $this->logout();

        $accept = ['password' => 'a-long-password-1', 'displayName' => 'X'];
        $this->request('POST', '/api/auth/invitations/accept', ['token' => $used] + $accept);
        self::assertResponseIsSuccessful();
        $this->logout();
        $this->request('POST', '/api/auth/invitations/accept', ['token' => $used] + $accept);
        $this->assertProblem(404, 'invitation_not_found');

        $this->request('POST', '/api/auth/invitations/accept', ['token' => Json::str($revokedBody, 'acceptToken')] + $accept);
        $this->assertProblem(404, 'invitation_not_found');

        $this->request('POST', '/api/auth/invitations/accept', ['token' => substr($expired, 0, -2).'xx'] + $accept);
        $this->assertProblem(404, 'invitation_not_found');
        $this->request('POST', '/api/auth/invitations/accept', ['token' => 'garbage'] + $accept);
        $this->assertProblem(404, 'invitation_not_found');
        $this->request('POST', '/api/auth/invitations/preview', ['token' => 'garbage']);
        $this->assertProblem(404, 'invitation_not_found');

        self::mockTime('+8 days');
        $this->request('POST', '/api/auth/invitations/accept', ['token' => $expired] + $accept);
        $this->assertProblem(404, 'invitation_not_found');
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM identity.user_account WHERE email LIKE '%@example.test' AND email <> 'jana@example.test'"));
    }

    public function test_re_inviting_replaces_the_pending_invitation_and_members_cannot_be_invited_again(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $first = $this->invite('klub', 'eva@example.test', 'FINANCE');
        $second = $this->invite('klub', 'eva@example.test', 'CHECK_IN');

        self::assertNotSame($first, $second);
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM identity.invitation WHERE revoked_at IS NOT NULL'));

        $this->request('POST', '/api/admin/org/klub/invitations', ['email' => 'jana@example.test', 'role' => 'FINANCE']);
        $this->assertProblem(409, 'already_member');
    }

    public function test_invitation_input_is_validated(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $this->request('POST', '/api/admin/org/klub/invitations', ['email' => 'nope', 'role' => 'GOD', 'eventScope' => ['x']]);
        $problem = $this->assertProblem(422, 'validation_failed');
        $paths = array_column(Json::rows($problem, 'violations'), 'path');
        self::assertContains('email', $paths);
        self::assertContains('role', $paths);

        // A finance member always sees all events: a scope makes no sense.
        $this->request('POST', '/api/admin/org/klub/invitations', ['email' => 'eva@example.test', 'role' => 'FINANCE', 'eventScope' => [self::EVENT_A]]);
        $this->assertProblem(422, 'invalid_event_scope');
    }

    public function test_the_owner_lists_members_and_changes_role_and_scope(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $member = $this->joinAs('jana@example.test', 'klub', 'eva@example.test', 'EVENT_MANAGER', [self::EVENT_A]);
        $this->logout();
        $this->login('jana@example.test');

        $list = $this->request('GET', '/api/admin/org/klub/members');
        $items = Json::rows($list, 'items');
        self::assertCount(2, $items);
        $eva = Json::where($items, 'email', 'eva@example.test');
        self::assertSame('EVENT_MANAGER', $eva['role']);
        self::assertSame([self::EVENT_A], $eva['eventScope']);

        $changed = $this->request('PUT', '/api/admin/org/klub/members/'.$member['membershipId'], ['role' => 'CHECK_IN', 'eventScope' => [self::EVENT_B]]);
        self::assertResponseIsSuccessful();
        $evaChanged = Json::where(Json::rows($changed, 'items'), 'email', 'eva@example.test');
        self::assertSame('CHECK_IN', $evaChanged['role']);
        self::assertSame([self::EVENT_B], $evaChanged['eventScope']);
        self::assertSame([self::EVENT_B], $this->connection()->fetchFirstColumn('SELECT event_id FROM identity.membership_event_scope'));

        $event = array_values(array_filter(
            $this->published(MembershipChanged::class),
            static fn(MembershipChanged $c): bool => 'EVENT_MANAGER' === $c->previousRole,
        ));
        self::assertCount(1, $event);
        self::assertSame('CHECK_IN', $event[0]->newRole);
        self::assertSame([self::EVENT_B], array_map(static fn(EventId $e): string => $e->toString(), $event[0]->eventScope));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'membership.changed'"));

        // Back to all events by dropping the scope; a role that must see everything refuses a scope.
        $this->request('PUT', '/api/admin/org/klub/members/'.$member['membershipId'], ['role' => 'CHECK_IN']);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM identity.membership_event_scope'));
        $this->request('PUT', '/api/admin/org/klub/members/'.$member['membershipId'], ['role' => 'FINANCE', 'eventScope' => [self::EVENT_A]]);
        $this->assertProblem(422, 'invalid_event_scope');
    }

    public function test_the_last_owner_cannot_be_demoted_or_removed_but_a_second_owner_makes_it_possible(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $this->request('PUT', '/api/admin/org/klub/members/'.$owner['membershipId'], ['role' => 'FINANCE']);
        $this->assertProblem(409, 'last_owner_required');
        $this->request('DELETE', '/api/admin/org/klub/members/'.$owner['membershipId']);
        $this->assertProblem(409, 'last_owner_required');

        $this->joinAs('jana@example.test', 'klub', 'eva@example.test', 'OWNER');
        $this->logout();
        $this->login('jana@example.test');
        $this->request('PUT', '/api/admin/org/klub/members/'.$owner['membershipId'], ['role' => 'FINANCE']);
        self::assertResponseIsSuccessful();
    }

    public function test_a_removed_member_loses_access_at_once_and_sees_a_404(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $member = $this->joinAs('jana@example.test', 'klub', 'eva@example.test', 'FINANCE', []);
        $this->request('GET', '/api/admin/org/klub');
        self::assertResponseIsSuccessful();
        $this->logout();

        $this->login('jana@example.test');
        $this->request('DELETE', '/api/admin/org/klub/members/'.$member['membershipId']);
        self::assertResponseStatusCodeSame(204);
        $this->logout();

        $this->login('eva@example.test', self::PASSWORD);
        $this->request('GET', '/api/admin/org/klub');
        $this->assertProblem(404, 'not_found');
        $profile = $this->request('GET', '/api/auth/me');
        self::assertSame([], Json::at($profile, 'memberships'));

        $revoked = array_values(array_filter(
            $this->published(MembershipChanged::class),
            static fn(MembershipChanged $c): bool => null === $c->newRole,
        ));
        self::assertCount(1, $revoked);
        self::assertSame('FINANCE', $revoked[0]->previousRole);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'membership.revoked'"));
    }

    public function test_unknown_members_and_invitations_are_404_and_foreign_ones_too(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $other = $this->createOwner('petr@example.test', 'cizi');
        $this->login('jana@example.test');

        $this->request('DELETE', '/api/admin/org/klub/members/0199a000-0000-7000-8000-000000000000');
        $this->assertProblem(404, 'membership_not_found');
        // A membership of another organizer is not reachable through my URL.
        $this->request('DELETE', '/api/admin/org/klub/members/'.$other['membershipId']);
        $this->assertProblem(404, 'membership_not_found');
        $this->request('PUT', '/api/admin/org/klub/members/'.$other['membershipId'], ['role' => 'FINANCE']);
        $this->assertProblem(404, 'membership_not_found');
        $this->request('DELETE', '/api/admin/org/klub/invitations/0199a000-0000-7000-8000-000000000000');
        $this->assertProblem(404, 'invitation_not_found');
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM identity.membership WHERE organizer_id = :o', ['o' => $other['organizerId']]));
    }

    public function test_only_the_owner_manages_the_team(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        foreach (['EVENT_MANAGER', 'FINANCE', 'CHECK_IN'] as $i => $role) {
            $email = strtolower($role).'@example.test';
            $member = $this->joinAs('jana@example.test', 'klub', $email, $role);

            $this->request('GET', '/api/admin/org/klub/members');
            $this->assertProblem(403, 'forbidden');
            $this->request('POST', '/api/admin/org/klub/invitations', ['email' => 'x'.$i.'@example.test', 'role' => 'OWNER']);
            $this->assertProblem(403, 'forbidden');
            $this->request('PUT', '/api/admin/org/klub/members/'.$member['membershipId'], ['role' => 'OWNER']);
            $this->assertProblem(403, 'forbidden');
            $this->request('DELETE', '/api/admin/org/klub/members/'.$member['membershipId']);
            $this->assertProblem(403, 'forbidden');
            $this->logout();
        }

        self::assertSame(4, $this->dbInt('SELECT count(*) FROM identity.membership'));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM identity.membership WHERE role = 'OWNER'"));
    }
}
