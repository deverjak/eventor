<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Identity;

use Eventor\Tests\Api\Identity\Support\IdentityApiTestCase;
use Eventor\Tests\Api\Support\ApiClient;
use Eventor\Tests\Integration\Identity\Support\Json;

/**
 * ORG-03 over real HTTP: invite -> accept -> the member sees the organizer; roles restrict; removal ends access.
 */
final class TeamApiTest extends IdentityApiTestCase
{
    private const string EVENT = '0199a000-0000-7000-8000-0000000000e1';

    public function test_invite_accept_and_the_member_sees_the_organizer(): void
    {
        $org = $this->signUp('team');
        $email = self::unique('eva').'@api-test.example';
        $token = $this->invite($org['client'], $org['slug'], $email, 'EVENT_MANAGER', [self::EVENT]);

        $pending = $org['client']->get("/api/admin/org/{$org['slug']}/invitations")->json();
        self::assertSame($email, Json::at($pending, 'items', 0, 'email'));

        $invitee = $this->anonymous();
        $preview = $invitee->post('/api/auth/invitations/preview', ['token' => $token]);
        self::assertSame(200, $preview->status, $preview->body);
        self::assertSame(['EVENT_MANAGER', false], [Json::at($preview->json(), 'role'), Json::at($preview->json(), 'accountExists')]);

        $accepted = $invitee->post('/api/auth/invitations/accept', ['token' => $token, 'password' => self::PASSWORD, 'displayName' => 'Eva']);
        self::assertSame(200, $accepted->status, $accepted->body);
        self::assertTrue(Json::at($accepted->json(), 'accountCreated'));
        self::assertTrue($invitee->hasSession());

        $me = $invitee->get('/api/auth/me')->json();
        self::assertSame($email, Json::at($me, 'email'));
        self::assertSame($org['slug'], Json::at($me, 'memberships', 0, 'slug'));
        self::assertSame('EVENT_MANAGER', Json::at($me, 'memberships', 0, 'role'));

        $detail = $invitee->get("/api/admin/org/{$org['slug']}");
        self::assertSame(200, $detail->status);
        self::assertSame(['EVENT_MANAGER', 2], [Json::at($detail->json(), 'yourRole'), Json::at($detail->json(), 'memberCount')]);

        $members = Json::rows($org['client']->get("/api/admin/org/{$org['slug']}/members")->json(), 'items');
        self::assertSame([self::EVENT], Json::where($members, 'email', $email)['eventScope']);
        self::assertSame([], Json::arr($org['client']->get("/api/admin/org/{$org['slug']}/invitations")->json(), 'items'));
    }

    public function test_an_existing_account_gets_a_second_organizer(): void
    {
        $first = $this->signUp('multi-a');
        $second = $this->signUp('multi-b');
        $token = $this->invite($second['client'], $second['slug'], $first['email'], 'FINANCE');

        $anonymous = $this->anonymous()->post('/api/auth/invitations/accept', ['token' => $token, 'password' => self::PASSWORD, 'displayName' => 'X']);
        $this->assertProblem($anonymous, 409, 'sign_in_required');

        $accepted = $first['client']->post('/api/auth/invitations/accept', ['token' => $token]);
        self::assertSame(200, $accepted->status, $accepted->body);
        self::assertFalse(Json::at($accepted->json(), 'accountCreated'));

        $roles = array_column(Json::rows($first['client']->get('/api/auth/me')->json(), 'memberships'), 'role', 'slug');
        self::assertSame([$first['slug'] => 'OWNER', $second['slug'] => 'FINANCE'], $roles);
        self::assertSame(200, $first['client']->get("/api/admin/org/{$second['slug']}")->status);
    }

    public function test_the_role_restricts_actions_with_a_403(): void
    {
        $org = $this->signUp('roles');
        $finance = $this->addMember($org, 'FINANCE');
        $checkIn = $this->addMember($org, 'CHECK_IN');

        self::assertSame(200, $finance['client']->get("/api/admin/org/{$org['slug']}")->status, 'every member sees the organizer');
        foreach ([$finance, $checkIn] as $member) {
            $this->assertProblem($member['client']->get("/api/admin/org/{$org['slug']}/members"), 403, 'forbidden');
            $this->assertProblem($member['client']->post("/api/admin/org/{$org['slug']}/invitations", ['email' => self::unique('x').'@api-test.example', 'role' => 'OWNER']), 403, 'forbidden');
            $this->assertProblem($member['client']->request('PUT', "/api/admin/org/{$org['slug']}/profile", ['name' => 'Hijacked']), 403, 'forbidden');
        }
        $this->assertProblem($checkIn['client']->get("/api/admin/org/{$org['slug']}/payment-settings"), 403, 'forbidden');
        self::assertSame('Org '.$org['slug'], Json::at($org['client']->get("/api/admin/org/{$org['slug']}/profile")->json(), 'name'));
    }

    public function test_a_removed_member_gets_a_404_for_the_organizer(): void
    {
        $org = $this->signUp('remove');
        $member = $this->addMember($org, 'FINANCE');
        self::assertSame(200, $member['client']->get("/api/admin/org/{$org['slug']}")->status);

        $removed = $org['client']->request('DELETE', "/api/admin/org/{$org['slug']}/members/{$member['membershipId']}");
        self::assertSame(204, $removed->status, $removed->body);

        $this->assertProblem($member['client']->get("/api/admin/org/{$org['slug']}"), 404, 'not_found');
        self::assertSame([], Json::at($member['client']->get('/api/auth/me')->json(), 'memberships'));
        $this->assertProblem($org['client']->request('DELETE', "/api/admin/org/{$org['slug']}/members/{$member['membershipId']}"), 404, 'membership_not_found');
    }

    public function test_changing_the_role_takes_effect_and_the_last_owner_stays(): void
    {
        $org = $this->signUp('change');
        $member = $this->addMember($org, 'FINANCE');
        $this->assertProblem($member['client']->get("/api/admin/org/{$org['slug']}/members"), 403, 'forbidden');

        $changed = $org['client']->request('PUT', "/api/admin/org/{$org['slug']}/members/{$member['membershipId']}", ['role' => 'OWNER']);
        self::assertSame(200, $changed->status, $changed->body);
        self::assertSame(200, $member['client']->get("/api/admin/org/{$org['slug']}/members")->status, 'the new role applies at once');

        $ownerMembership = Json::str(Json::where(Json::rows($org['client']->get("/api/admin/org/{$org['slug']}/members")->json(), 'items'), 'email', $org['email']), 'membershipId');
        self::assertSame(200, $org['client']->request('PUT', "/api/admin/org/{$org['slug']}/members/$ownerMembership", ['role' => 'FINANCE'])->status);
        $this->assertProblem($member['client']->request('PUT', "/api/admin/org/{$org['slug']}/members/{$member['membershipId']}", ['role' => 'FINANCE']), 409, 'last_owner_required');
    }

    public function test_bad_invitations_are_refused(): void
    {
        $org = $this->signUp('bad');
        $token = $this->invite($org['client'], $org['slug'], self::unique('a').'@api-test.example', 'CHECK_IN');

        $this->assertProblem($this->anonymous()->post('/api/auth/invitations/accept', ['token' => 'inv1_nope', 'password' => self::PASSWORD, 'displayName' => 'X']), 404, 'invitation_not_found');
        $this->assertProblem($this->anonymous()->post('/api/auth/invitations/preview', ['token' => substr($token, 0, -3).'AAA']), 404, 'invitation_not_found');
        $this->assertProblem($this->anonymous()->post('/api/auth/invitations/accept', ['token' => $token]), 422, 'account_details_required');
        $this->assertProblem($org['client']->post("/api/admin/org/{$org['slug']}/invitations", ['email' => 'nope', 'role' => 'GOD']), 422, 'validation_failed');
        $this->assertProblem($org['client']->post("/api/admin/org/{$org['slug']}/invitations", ['email' => self::unique('b').'@api-test.example', 'role' => 'FINANCE', 'eventScope' => [self::EVENT]]), 422, 'invalid_event_scope');
        $this->assertProblem($org['client']->post("/api/admin/org/{$org['slug']}/invitations", ['email' => $org['email'], 'role' => 'FINANCE']), 409, 'already_member');
        $this->assertProblem($this->anonymous()->post("/api/admin/org/{$org['slug']}/invitations", ['email' => 'x@api-test.example', 'role' => 'FINANCE']), 401, 'unauthorized');

        $revoke = $org['client']->request('DELETE', '/api/admin/org/'.$org['slug'].'/invitations/'.$this->pendingId($org['client'], $org['slug']));
        self::assertSame(204, $revoke->status);
        $this->assertProblem($this->anonymous()->post('/api/auth/invitations/accept', ['token' => $token, 'password' => self::PASSWORD, 'displayName' => 'X']), 404, 'invitation_not_found');
    }

    private function pendingId(ApiClient $owner, string $slug): string
    {
        return Json::str($owner->get("/api/admin/org/$slug/invitations")->json(), 'items', 0, 'invitationId');
    }
}
