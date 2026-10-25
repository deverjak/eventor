<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity;

use Eventor\Identity\Application\Invite\AcceptInvitation;
use Eventor\Identity\Application\Invite\InviteMember;
use Eventor\Identity\Application\SignUp\SignUp;
use Eventor\Identity\Application\Team\ChangeMembership;
use Eventor\Identity\Application\Team\RemoveMember;
use Eventor\Identity\Contract\AccessPolicy;
use Eventor\Identity\Contract\Event\MembershipChanged;
use Eventor\Identity\Contract\Exception\OrganizerNotFound;
use Eventor\Identity\Contract\OrganizerLookup;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\Domain\Account\EmailAlreadyRegistered;
use Eventor\Identity\Domain\Membership\AlreadyMember;
use Eventor\Identity\Domain\Membership\LastOwnerRequired;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Tests\Integration\Identity\Support\RecordingEventBus;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * Command handlers and Contract facades of the Identity module, below HTTP: domain event, database effects, audit and
 * the integration events in the outbox.
 */
final class IdentityCommandsTest extends IntegrationTestCase
{
    /**
     * @return list<MembershipChanged>
     */
    private function membershipChanges(): array
    {
        return RecordingEventBus::dispatched(MembershipChanged::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        RecordingEventBus::reset();
    }

    public function test_sign_up_publishes_membership_changed_and_audits_with_an_anonymous_actor(): void
    {
        /** @var array{organizerId: string, userAccountId: string} $ids */
        $ids = $this->commandBus()->dispatch(new SignUp('nova@example.test', 'correct-horse-battery', 'Nova', 'Nový klub', 'novy-klub'));

        $changes = $this->membershipChanges();
        self::assertCount(1, $changes);
        self::assertNull($changes[0]->previousRole);
        self::assertSame('OWNER', $changes[0]->newRole);
        self::assertSame($ids['userAccountId'], $changes[0]->userAccountId->toString());
        self::assertSame($ids['organizerId'], $changes[0]->organizerId->toString());
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'membership.granted' AND organizer_id = :o", ['o' => $ids['organizerId']]));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM identity.organizer_policy WHERE organizer_id = :o', ['o' => $ids['organizerId']]));
    }

    public function test_sign_up_with_a_registered_email_fails_without_side_effects(): void
    {
        $this->createOwner('nova@example.test', 'prvni');

        $this->expectException(EmailAlreadyRegistered::class);
        try {
            $this->commandBus()->dispatch(new SignUp('NOVA@example.test', 'correct-horse-battery', 'Nova', 'Druhý', 'druhy'));
        } finally {
            self::assertSame(1, $this->dbInt('SELECT count(*) FROM identity.organizer'));
        }
    }

    public function test_the_event_scope_is_honoured_by_the_access_policy(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub');
        $organizer = OrganizerId::fromString($owner['organizerId']);
        $mine = EventId::generate();
        $other = EventId::generate();
        $stamp = TenantStamp::of($organizer);

        /** @var array{token: string} $invite */
        $invite = $this->commandBus()->dispatch(new InviteMember($organizer, 'em@example.test', 'EVENT_MANAGER', [$mine], UserAccountId::fromString($owner['userAccountId'])), $stamp);
        $this->commandBus()->dispatch(new AcceptInvitation($invite['token'], null, 'em-password-1234', 'Eva'));
        /** @var array{token: string} $free */
        $free = $this->commandBus()->dispatch(new InviteMember($organizer, 'free@example.test', 'EVENT_MANAGER', [], UserAccountId::fromString($owner['userAccountId'])), $stamp);
        $this->commandBus()->dispatch(new AcceptInvitation($free['token'], null, 'free-password-12', 'Free'));

        $policy = $this->service(AccessPolicy::class);
        self::assertSame('EVENT_MANAGER', $policy->roleOf($organizer, 'em@example.test'));
        self::assertTrue($policy->isAllowed(Permission::EventEdit, $organizer, 'em@example.test'));
        self::assertTrue($policy->isAllowedForEvent(Permission::EventEdit, $organizer, $mine, 'em@example.test'));
        self::assertFalse($policy->isAllowedForEvent(Permission::EventEdit, $organizer, $other, 'em@example.test'), 'outside the scope');
        self::assertFalse($policy->isAllowedForEvent(Permission::TeamManage, $organizer, $mine, 'em@example.test'), 'role lacks the permission');
        self::assertTrue($policy->eventScope($organizer, 'em@example.test')[0]->equals($mine));

        self::assertTrue($policy->isAllowedForEvent(Permission::EventEdit, $organizer, $other, 'free@example.test'));
        self::assertSame([], $policy->eventScope($organizer, 'free@example.test'));
        self::assertTrue($policy->isAllowedForEvent(Permission::EventEdit, $organizer, $other, 'jana@example.test'), 'the owner is never restricted');
        self::assertFalse($policy->isAllowedForEvent(Permission::EventEdit, $organizer, $mine, 'stranger@example.test'));
        self::assertSame([], $policy->eventScope($organizer, 'stranger@example.test'));
    }

    public function test_membership_commands_publish_membership_changed_with_previous_and_new_role(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub');
        $organizer = OrganizerId::fromString($owner['organizerId']);
        $stamp = TenantStamp::of($organizer);
        /** @var array{token: string} $invite */
        $invite = $this->commandBus()->dispatch(new InviteMember($organizer, 'fin@example.test', 'FINANCE', [], UserAccountId::fromString($owner['userAccountId'])), $stamp);
        /** @var array{membershipId: string} $accepted */
        $accepted = $this->commandBus()->dispatch(new AcceptInvitation($invite['token'], null, 'fin-password-1234', 'Fin'));
        $membership = MembershipId::fromString($accepted['membershipId']);

        $this->commandBus()->dispatch(new ChangeMembership($organizer, $membership, 'CHECK_IN', [EventId::generate()]), $stamp);
        $this->commandBus()->dispatch(new RemoveMember($organizer, $membership), $stamp);

        $pairs = array_map(static fn(MembershipChanged $c): string => ($c->previousRole ?? '-').':'.($c->newRole ?? '-'), $this->membershipChanges());
        self::assertSame(['-:OWNER', '-:FINANCE', 'FINANCE:CHECK_IN', 'CHECK_IN:-'], $pairs);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM identity.membership_event_scope'));
    }

    public function test_a_member_cannot_be_invited_twice_and_the_last_owner_stays(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub');
        $organizer = OrganizerId::fromString($owner['organizerId']);
        $stamp = TenantStamp::of($organizer);

        try {
            $this->commandBus()->dispatch(new InviteMember($organizer, 'JANA@example.test', 'FINANCE', [], UserAccountId::fromString($owner['userAccountId'])), $stamp);
            self::fail('already a member');
        } catch (AlreadyMember) {
        }

        $this->expectException(LastOwnerRequired::class);
        $this->commandBus()->dispatch(new RemoveMember($organizer, MembershipId::fromString($owner['membershipId'])), $stamp);
    }

    public function test_the_organizer_lookup_resolves_by_slug_and_id_without_a_member(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub', 'Klub Plzeň');
        $lookup = $this->service(OrganizerLookup::class);

        $bySlug = $lookup->findBySlug('klub');
        self::assertNotNull($bySlug);
        self::assertSame($owner['organizerId'], $bySlug->id->toString());
        self::assertSame('Klub Plzeň', $lookup->get($bySlug->id)->name);
        self::assertNull($lookup->findBySlug('neexistuje'));

        $this->expectException(OrganizerNotFound::class);
        $lookup->get(OrganizerId::generate());
    }

    public function test_the_settings_facade_reads_contact_payment_and_policy_and_defaults_without_a_policy_row(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub', 'Klub Plzeň');
        $organizer = OrganizerId::fromString($owner['organizerId']);
        $this->connection()->executeStatement('DELETE FROM identity.organizer_policy');
        $settings = $this->service(OrganizerSettings::class);

        $contact = $settings->contact($organizer);
        self::assertSame('klub', $contact->slug);
        self::assertSame('Klub Plzeň', $contact->name);
        self::assertSame('jana@example.test', $contact->contactEmail);

        $payment = $settings->paymentSettings($organizer);
        self::assertFalse($payment->canIssueTransferPayments());
        self::assertNull($payment->pairrotSubtenantId);

        $policy = $settings->policy($organizer);
        self::assertSame(14, $policy->defaultDueDays);
        self::assertSame(48, $policy->defaultHoldTtlHours);
        self::assertFalse($policy->autoRestoreLatePayment);

        foreach ([static fn() => $settings->contact(OrganizerId::generate()), static fn() => $settings->policy(OrganizerId::generate()), static fn() => $settings->paymentSettings(OrganizerId::generate())] as $call) {
            try {
                $call();
                self::fail('expected OrganizerNotFound');
            } catch (OrganizerNotFound) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
