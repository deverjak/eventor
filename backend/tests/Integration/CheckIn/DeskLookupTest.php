<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\CheckIn;

use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\CheckIn\CheckInHttpTestCase;

/**
 * CHK-02 (search, QR), CHK-03 (the signed token), CHK-04 (the detail) and CHK-09 (the verdict) over HTTP.
 */
final class DeskLookupTest extends CheckInHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->openDesk();
        $this->login(self::OWNER);
    }

    /**
     * @param array<string, mixed> $reference
     *
     * @return array<array-key, mixed>|null
     */
    private function lookup(array $reference, ?string $list = null): ?array
    {
        return $this->post($this->teamPath('/lists/'.($list ?? $this->trailList).'/lookup'), $reference);
    }

    public function test_search_finds_a_participant_by_name_and_by_registration_code(): void
    {
        $alena = $this->participant('Alena');
        $this->participant('Bedřich');

        $byName = $this->request('GET', $this->teamPath('/lists/'.$this->trailList.'/participants?q=alen'));
        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, Json::arr($byName, 'items'));
        self::assertSame($alena['participationId'], Json::str($byName, 'items.0.participationId'));
        self::assertSame($alena['code'], Json::str($byName, 'items.0.registrationCode'));
        self::assertSame('confirmed', Json::str($byName, 'items.0.state'));
        self::assertNull(Json::at($byName, 'items.0.checkedInAt'));

        $byCode = $this->request('GET', $this->teamPath('/lists/'.$this->trailList.'/participants?q='.$alena['code']));
        self::assertSame($alena['participationId'], Json::str($byCode, 'items.0.participationId'));
    }

    public function test_search_shows_who_has_arrived(): void
    {
        $paid = $this->paidParticipant('Alena');
        $this->login(self::OWNER);
        $this->post($this->teamPath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $paid['participationId']]);

        $found = $this->request('GET', $this->teamPath('/lists/'.$this->trailList.'/participants?q=alena'));

        self::assertNotNull(Json::at($found, 'items.0.checkedInAt'));
    }

    public function test_search_looks_only_in_the_activity_of_the_list(): void
    {
        $this->participant('Alena');

        $found = $this->request('GET', $this->teamPath('/lists/'.$this->kidsList.'/participants?q=alena'));

        self::assertSame([], Json::arr($found, 'items'));
    }

    public function test_a_short_term_finds_nothing(): void
    {
        $this->participant('Alena');

        $found = $this->request('GET', $this->teamPath('/lists/'.$this->trailList.'/participants?q=a'));

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], Json::arr($found, 'items'));
    }

    public function test_search_of_an_unknown_list_is_not_found(): void
    {
        $this->request('GET', $this->teamPath('/lists/0199a000-0000-7000-8000-0000000000bb/participants?q=alena'));

        $this->assertProblem(404, 'check_in_list_not_found');
    }

    public function test_search_of_a_list_of_another_event_is_not_found(): void
    {
        $this->request('GET', $this->teamPath('/lists/'.$this->trailList.'/participants?q=alena', eventId: '0199a000-0000-7000-8000-0000000000aa'));

        $this->assertProblem(404, 'check_in_list_not_found');
    }

    public function test_search_needs_a_session(): void
    {
        $this->client->getCookieJar()->clear();

        $this->request('GET', $this->teamPath('/lists/'.$this->trailList.'/participants?q=alena'));

        $this->assertProblem(401, 'unauthorized');
    }

    public function test_search_of_a_foreign_organizer_is_not_found(): void
    {
        $this->signInAsForeignOwner();

        $this->request('GET', $this->teamPath('/lists/'.$this->trailList.'/participants?q=alena'));

        $this->assertProblem(404, 'not_found');
    }

    public function test_an_unpaid_participant_is_a_warning_with_the_cash_option_and_the_checkin_detail(): void
    {
        $jana = $this->participant('Jana');

        $detail = $this->lookup(['participationId' => $jana['participationId']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('warn', Json::str($detail, 'decision'));
        self::assertSame('unpaid', Json::str($detail, 'situation'));
        self::assertSame($jana['participationId'], Json::str($detail, 'participationId'));
        self::assertSame('Jana Nováková', Json::str($detail, 'displayName'));
        self::assertSame('Trail 5 km', Json::str($detail, 'activityName'));
        self::assertSame('Jednotlivec', Json::str($detail, 'variantName'));
        self::assertSame('confirmed', Json::str($detail, 'state'));
        self::assertSame('unpaid', Json::str($detail, 'financialState'));
        self::assertSame(30000, Json::int($detail, 'amountDueMinor'));
        self::assertSame(0, Json::int($detail, 'receivedMinor'));
        self::assertSame([], Json::arr($detail, 'missingConfirmations'));
        self::assertSame([], Json::arr($detail, 'otherParticipations'));
        self::assertNull(Json::at($detail, 'checkIn'));
        self::assertTrue(Json::at($detail, 'cash.available'));
        self::assertSame(30000, Json::int($detail, 'cash.outstandingMinor'));
        foreach (['birthDate', 'email', 'phone', 'answers'] as $private) {
            self::assertFalse(Json::has($detail, $private), 'the check-in projection carries no '.$private);
        }
    }

    public function test_a_paid_participant_is_allowed_without_a_cash_offer(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);

        $detail = $this->lookup(['participationId' => $paid['participationId']]);

        self::assertSame('allow', Json::str($detail, 'decision'));
        self::assertNull(Json::at($detail, 'situation'));
        self::assertNull(Json::at($detail, 'cash'));
        self::assertSame('paid', Json::str($detail, 'financialState'));
    }

    public function test_a_participant_is_found_by_the_signed_qr_token_and_by_the_registration_code(): void
    {
        $jana = $this->participant();

        $byQr = $this->lookup(['qr' => $this->qrOf($jana['participationId'])]);
        $byCode = $this->lookup(['code' => strtolower($jana['code'])]);

        self::assertSame($jana['participationId'], Json::str($byQr, 'participationId'));
        self::assertSame($jana['participationId'], Json::str($byCode, 'participationId'));
    }

    public function test_a_forged_qr_token_is_refused(): void
    {
        $jana = $this->participant();
        $token = $this->qrOf($jana['participationId']);

        $this->lookup(['qr' => substr($token, 0, -3).'AAA']);
        $this->assertProblem(422, 'qr_token_invalid');

        $this->lookup(['qr' => 'qr1.garbage.garbage']);
        $this->assertProblem(422, 'qr_token_invalid');
    }

    public function test_an_unknown_registration_code_is_not_found(): void
    {
        $this->lookup(['code' => 'NOSUCHCODE']);

        $this->assertProblem(404, 'not_found');
    }

    public function test_a_participation_of_another_organizer_is_not_found(): void
    {
        $this->lookup(['participationId' => '0199a000-0000-7000-8000-0000000000cc']);

        $this->assertProblem(404, 'participation_not_found');
    }

    public function test_exactly_one_reference_is_required(): void
    {
        $jana = $this->participant();

        $this->lookup([]);
        $this->assertProblem(422, 'validation_failed');

        $this->lookup(['code' => $jana['code'], 'participationId' => $jana['participationId']]);
        $this->assertProblem(422, 'validation_failed');

        $this->lookup(['participationId' => 'not-a-uuid']);
        $this->assertProblem(422, 'validation_failed');
    }

    public function test_a_cancelled_participation_is_blocked(): void
    {
        $jana = $this->participant();
        $this->setParticipationColumn($jana['participationId'], 'state', 'cancelled');

        $detail = $this->lookup(['qr' => $this->qrOf($jana['participationId'])]);

        self::assertSame('block', Json::str($detail, 'decision'));
        self::assertSame('cancelled_or_expired', Json::str($detail, 'situation'));
    }

    public function test_a_participation_of_another_activity_is_blocked_on_this_list(): void
    {
        $jana = $this->participant();

        $detail = $this->lookup(['participationId' => $jana['participationId']], $this->kidsList);

        self::assertSame('block', Json::str($detail, 'decision'));
        self::assertSame('wrong_activity', Json::str($detail, 'situation'));
        self::assertSame('Trail 5 km', Json::str($detail, 'activityName'), 'the desk shows where the participant belongs');
    }

    public function test_a_missing_confirmation_is_escalated(): void
    {
        $paid = $this->paidParticipant();
        $this->setParticipationColumn($paid['participationId'], 'legal_state', 'incomplete');
        $this->login(self::OWNER);

        $detail = $this->lookup(['participationId' => $paid['participationId']]);

        self::assertSame('escalate', Json::str($detail, 'decision'));
        self::assertSame('missing_confirmation', Json::str($detail, 'situation'));
    }

    public function test_a_participation_that_requires_attention_is_escalated(): void
    {
        $paid = $this->paidParticipant();
        $this->setParticipationColumn($paid['participationId'], 'state', 'requires_attention');
        $this->login(self::OWNER);

        $detail = $this->lookup(['participationId' => $paid['participationId']]);

        self::assertSame('escalate', Json::str($detail, 'decision'));
        self::assertSame('requires_attention', Json::str($detail, 'situation'));
    }

    public function test_incomplete_answers_are_escalated(): void
    {
        $paid = $this->paidParticipant();
        $this->setParticipationColumn($paid['participationId'], 'answers_state', 'incomplete');
        $this->login(self::OWNER);

        $detail = $this->lookup(['participationId' => $paid['participationId']]);

        self::assertSame('escalate', Json::str($detail, 'decision'));
        self::assertSame('requires_attention', Json::str($detail, 'situation'));
    }

    public function test_an_arrived_participant_shows_when_and_by_whom(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);
        $this->post($this->teamPath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $paid['participationId']]);

        $detail = $this->lookup(['participationId' => $paid['participationId']]);

        self::assertSame('block', Json::str($detail, 'decision'));
        self::assertSame('already_checked_in', Json::str($detail, 'situation'));
        self::assertSame($this->owner['userAccountId'], Json::str($detail, 'checkIn.checkedInBy'));
        self::assertNotNull(Json::at($detail, 'checkIn.checkedInAt'));
        self::assertNull(Json::at($detail, 'checkIn.deviceLabel'));
    }

    public function test_the_other_participations_of_the_person_are_listed(): void
    {
        $jana = $this->participant();
        $this->connection()->executeStatement(
            "INSERT INTO registration.participation (id, organizer_id, order_id, person_id, event_id, activity_id, variant_id, state, registration_code, price_minor, price_currency, created_at)
             SELECT ?, organizer_id, order_id, person_id, event_id, ?, ?, 'confirmed', 'KIDSCODE01', 10000, 'CZK', created_at FROM registration.participation WHERE id = ?",
            ['0199a000-0000-7000-8000-0000000000dd', $this->event['kids'], $this->event['kidsVariant'], $jana['participationId']],
        );

        $detail = $this->lookup(['participationId' => $jana['participationId']]);

        self::assertCount(1, Json::arr($detail, 'otherParticipations'));
        self::assertSame('0199a000-0000-7000-8000-0000000000dd', Json::str($detail, 'otherParticipations.0.participationId'));
        self::assertSame('Dětský závod', Json::str($detail, 'otherParticipations.0.activityName'));
    }

    public function test_a_device_token_looks_a_participant_up(): void
    {
        $jana = $this->participant();
        $token = $this->issueDeviceToken($this->trailList)['token'];

        $detail = $this->post($this->devicePath('/lists/'.$this->trailList.'/lookup'), ['qr' => $this->qrOf($jana['participationId'])], self::bearer($token));

        self::assertResponseStatusCodeSame(200);
        self::assertSame('warn', Json::str($detail, 'decision'));
        self::assertFalse(Json::at($detail, 'cash.available'), 'a device may take cash only when the organizer policy says so');
    }

    public function test_a_device_bound_to_another_list_is_forbidden(): void
    {
        $jana = $this->participant();
        $token = $this->issueDeviceToken($this->kidsList)['token'];

        $this->post($this->devicePath('/lists/'.$this->trailList.'/lookup'), ['participationId' => $jana['participationId']], self::bearer($token));

        $this->assertProblem(403, 'forbidden');
    }

    public function test_the_cash_offer_follows_the_policy_for_a_device(): void
    {
        $jana = $this->participant();
        $this->allowDevicesToTakeCash();
        $token = $this->issueDeviceToken()['token'];

        $detail = $this->post($this->devicePath('/lists/'.$this->trailList.'/lookup'), ['participationId' => $jana['participationId']], self::bearer($token));

        self::assertTrue(Json::at($detail, 'cash.available'));
    }
}
