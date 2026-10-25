<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Identity;

use Eventor\Tests\Api\Identity\Support\IdentityApiTestCase;
use Eventor\Tests\Integration\Identity\Support\Json;

/**
 * ORG-01/02/05 settings and the check-in device tokens over real HTTP.
 */
final class SettingsAndDevicesApiTest extends IdentityApiTestCase
{
    private const string LIST = '0199a000-0000-7000-8000-0000000000c9';

    public function test_profile_round_trip(): void
    {
        $org = $this->signUp('profile');
        $path = "/api/admin/org/{$org['slug']}/profile";

        self::assertSame($org['email'], Json::at($org['client']->get($path)->json(), 'contactEmail'), 'the owner e-mail is the default contact');

        $saved = $org['client']->request('PUT', $path, ['name' => 'Nový název', 'contactEmail' => 'info@klub.example', 'companyId' => '12345678', 'billingCountry' => 'CZ', 'brandColor' => '#aabbcc']);
        self::assertSame(200, $saved->status, $saved->body);

        $profile = $org['client']->get($path)->json();
        self::assertSame(
            ['Nový název', 'info@klub.example', '12345678', 'CZ', '#aabbcc', $org['slug']],
            [Json::at($profile, 'name'), Json::at($profile, 'contactEmail'), Json::at($profile, 'companyId'), Json::at($profile, 'billingCountry'), Json::at($profile, 'brandColor'), Json::at($profile, 'slug')],
        );

        $this->assertProblem($org['client']->request('PUT', $path, ['name' => '', 'brandColor' => 'blue']), 422, 'validation_failed');
        $this->assertProblem($this->anonymous()->get($path), 401, 'unauthorized');
    }

    public function test_payment_settings_round_trip_and_validation(): void
    {
        $org = $this->signUp('pay');
        $path = "/api/admin/org/{$org['slug']}/payment-settings";

        $defaults = $org['client']->get($path)->json();
        self::assertSame(
            [14, 48, false, 'ask', false],
            [Json::at($defaults, 'defaultDueDays'), Json::at($defaults, 'defaultHoldTtlHours'), Json::at($defaults, 'autoRestoreLatePayment'), Json::at($defaults, 'overpaymentHandling'), Json::at($defaults, 'pairrotConnected')],
        );

        $saved = $org['client']->request('PUT', $path, ['defaultDueDays' => 10, 'defaultHoldTtlHours' => 72, 'autoRestoreLatePayment' => true, 'overpaymentHandling' => 'donation', 'checkInMayRecordCash' => true]);
        self::assertSame(200, $saved->status, $saved->body);
        $body = $saved->json();
        self::assertSame(
            [10, 72, true, 'donation', true],
            [Json::at($body, 'defaultDueDays'), Json::at($body, 'defaultHoldTtlHours'), Json::at($body, 'autoRestoreLatePayment'), Json::at($body, 'overpaymentHandling'), Json::at($body, 'checkInMayRecordCash')],
        );
        self::assertSame(10, Json::at($org['client']->get($path)->json(), 'defaultDueDays'));

        $this->assertProblem($org['client']->request('PUT', $path, ['defaultDueDays' => 500]), 422, 'validation_failed');
        $this->assertProblem($this->addMember($org, 'EVENT_MANAGER')['client']->get($path), 403, 'forbidden');
    }

    public function test_the_pairrot_wizard_needs_valid_input_and_the_owner(): void
    {
        $org = $this->signUp('pairrot');
        $path = "/api/admin/org/{$org['slug']}/payment-settings/pairrot";

        $this->assertProblem($org['client']->post($path, ['accountNumberOrIban' => '']), 422, 'validation_failed');
        $this->assertProblem($this->addMember($org, 'FINANCE')['client']->post($path, ['accountNumberOrIban' => 'CZ6508000000192000145399']), 403, 'forbidden');
        $this->assertProblem($this->anonymous()->post($path, ['accountNumberOrIban' => 'CZ6508000000192000145399']), 401, 'unauthorized');
    }

    public function test_issue_a_device_token_and_use_it_on_the_check_in_api(): void
    {
        $org = $this->signUp('device');
        $issued = $org['client']->post("/api/admin/org/{$org['slug']}/device-tokens", ['label' => 'Gate A', 'checkInListId' => self::LIST]);
        self::assertSame(201, $issued->status, $issued->body);
        $token = Json::str($issued->json(), 'token');
        self::assertStringStartsWith('dev1_', $token);

        $who = $this->anonymous()->get('/api/check-in/whoami', ['Authorization' => 'Bearer '.$token]);

        self::assertSame(200, $who->status, $who->body);
        self::assertSame(['device', 'Gate A', self::LIST], [Json::at($who->json(), 'type'), Json::at($who->json(), 'label'), Json::at($who->json(), 'checkInListId')]);
        self::assertSame(Json::at($org['client']->get("/api/admin/org/{$org['slug']}")->json(), 'id'), Json::at($who->json(), 'organizerId'));

        $list = Json::rows($org['client']->get("/api/admin/org/{$org['slug']}/device-tokens")->json(), 'items');
        self::assertSame([Json::at($issued->json(), 'id')], array_column($list, 'id'));
        self::assertStringNotContainsString($token, json_encode($list, \JSON_THROW_ON_ERROR));
    }

    public function test_revoked_and_unknown_tokens_and_anonymous_calls_are_401(): void
    {
        $org = $this->signUp('revoke');
        $issued = $org['client']->post("/api/admin/org/{$org['slug']}/device-tokens", ['label' => 'Gate B'])->json();
        $id = Json::str($issued, 'id');
        $bearer = ['Authorization' => 'Bearer '.Json::str($issued, 'token')];
        self::assertSame(200, $this->anonymous()->get('/api/check-in/whoami', $bearer)->status);

        $revoke = $org['client']->request('DELETE', "/api/admin/org/{$org['slug']}/device-tokens/$id");
        self::assertSame(204, $revoke->status);

        $this->assertProblem($this->anonymous()->get('/api/check-in/whoami', $bearer), 401, 'invalid_device_token');
        $this->assertProblem($this->anonymous()->get('/api/check-in/whoami', ['Authorization' => 'Bearer dev1_garbage']), 401, 'invalid_device_token');
        $this->assertProblem($this->anonymous()->get('/api/check-in/whoami'), 401, 'unauthorized');
        $this->assertProblem($this->anonymous()->get("/api/admin/org/{$org['slug']}", $bearer), 401, 'unauthorized');
        $this->assertProblem($org['client']->request('DELETE', "/api/admin/org/{$org['slug']}/device-tokens/$id"), 404, 'device_token_not_found');
        $this->assertProblem($org['client']->request('DELETE', "/api/admin/org/{$org['slug']}/device-tokens/0199a000-0000-7000-8000-000000000000"), 404, 'device_token_not_found');
    }

    public function test_a_team_member_session_reaches_whoami_and_device_issuing_needs_permission(): void
    {
        $org = $this->signUp('whoami');
        $who = $org['client']->get('/api/check-in/whoami');
        self::assertSame(200, $who->status);
        self::assertSame(['member', $org['email']], [Json::at($who->json(), 'type'), Json::at($who->json(), 'profile', 'email')]);

        $door = $this->addMember($org, 'CHECK_IN');
        $this->assertProblem($door['client']->post("/api/admin/org/{$org['slug']}/device-tokens", ['label' => 'Mine']), 403, 'forbidden');
        $this->assertProblem($org['client']->post("/api/admin/org/{$org['slug']}/device-tokens", ['label' => '']), 422, 'validation_failed');
    }
}
