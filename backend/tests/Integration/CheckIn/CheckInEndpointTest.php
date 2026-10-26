<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\CheckIn;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\CheckIn\CheckInHttpTestCase;

/**
 * CHK-05, CHK-06, CHK-09 over HTTP: `POST .../lists/{listId}/check-ins` in both auth modes, the matrix outcomes, the
 * "already checked in" answer and the idempotency of a repeated request.
 */
final class CheckInEndpointTest extends CheckInHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->openDesk();
        $this->login(self::OWNER);
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     *
     * @return array<array-key, mixed>|null
     */
    private function checkIn(array $body, ?string $list = null, array $headers = [], ?string $key = null): ?array
    {
        return $this->post($this->teamPath('/lists/'.($list ?? $this->trailList).'/check-ins'), $body, $headers, $key);
    }

    public function test_a_paid_participant_checks_in(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);

        $result = $this->checkIn(['participationId' => $paid['participationId']]);

        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertSame('checked_in', Json::str($result, 'outcome'));
        $row = $this->dbRow('SELECT * FROM checkin.check_in WHERE participation_id = ?', [$paid['participationId']]);
        self::assertSame(Json::str($result, 'checkInId'), $row['id']);
        self::assertSame($this->trailList, $row['list_id']);
        self::assertSame($this->owner['userAccountId'], $row['checked_in_by'], 'the account id of the operator, never an e-mail');
        self::assertNull($row['device_label']);
        self::assertNull($row['cash_minor']);
        self::assertNull($row['voided_at']);
        self::assertSame($this->owner['organizerId'], $row['organizer_id']);
    }

    public function test_the_participant_can_be_identified_by_qr_token_and_by_code(): void
    {
        $byQr = $this->paidParticipant('Alena');
        $byCode = $this->paidParticipant('Bedřich');
        $this->login(self::OWNER);

        $this->checkIn(['qr' => $this->qrOf($byQr['participationId'])]);
        self::assertResponseStatusCodeSame(201);
        $this->checkIn(['code' => $byCode['code']]);
        self::assertResponseStatusCodeSame(201);

        self::assertSame(1, $this->validCheckIns($byQr['participationId']));
        self::assertSame(1, $this->validCheckIns($byCode['participationId']));
    }

    public function test_the_station_is_recorded(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);
        $station = '0199a000-0000-7000-8000-0000000000ee';

        $this->checkIn(['participationId' => $paid['participationId'], 'stationId' => $station]);

        self::assertSame($station, $this->dbString('SELECT station_id FROM checkin.check_in WHERE participation_id = ?', [$paid['participationId']]));
    }

    public function test_a_repeated_request_with_the_same_key_returns_the_first_answer(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);
        $key = $this->key();

        $first = $this->checkIn(['participationId' => $paid['participationId']], key: $key);
        $again = $this->checkIn(['participationId' => $paid['participationId']], key: $key);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(Json::str($first, 'checkInId'), Json::str($again, 'checkInId'));
        self::assertSame(1, $this->validCheckIns($paid['participationId']));
    }

    public function test_the_second_attempt_shows_when_and_by_whom(): void
    {
        self::mockTime('2026-11-21 09:41:00 Europe/Prague');
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);
        $first = $this->checkIn(['participationId' => $paid['participationId']]);

        $this->checkIn(['participationId' => $paid['participationId']]);

        $problem = $this->assertProblem(409, 'already_checked_in');
        self::assertSame(Json::str($first, 'checkedInAt'), Json::str($problem, 'context.checkedInAt'));
        self::assertSame($this->owner['userAccountId'], Json::str($problem, 'context.checkedInBy'));
        self::assertSame(1, $this->validCheckIns($paid['participationId']));
    }

    public function test_an_unpaid_participant_needs_the_warning_to_be_acknowledged(): void
    {
        $jana = $this->participant();

        $this->checkIn(['participationId' => $jana['participationId']]);
        $problem = $this->assertProblem(409, 'check_in_warning_unacknowledged');
        self::assertSame('unpaid', Json::str($problem, 'context.situation'));
        self::assertSame(['acknowledge_warning', 'record_cash'], array_column(Json::arr($problem, 'nextSteps'), 'action'));
        self::assertSame(0, $this->validCheckIns($jana['participationId']));

        $result = $this->checkIn(['participationId' => $jana['participationId'], 'acknowledgeWarning' => true]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('unpaid', Json::str($result, 'situation'));
        self::assertSame(1, $this->validCheckIns($jana['participationId']));
    }

    public function test_a_cancelled_participation_is_blocked(): void
    {
        $paid = $this->paidParticipant();
        $this->setParticipationColumn($paid['participationId'], 'state', 'cancelled');
        $this->login(self::OWNER);

        $this->checkIn(['participationId' => $paid['participationId']]);

        $problem = $this->assertProblem(409, 'check_in_blocked');
        self::assertSame('cancelled_or_expired', Json::str($problem, 'context.situation'));
        self::assertSame(0, $this->validCheckIns($paid['participationId']));
    }

    public function test_a_participant_of_another_activity_is_blocked_on_this_list(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);

        $this->checkIn(['participationId' => $paid['participationId']], $this->kidsList);

        $problem = $this->assertProblem(409, 'check_in_blocked');
        self::assertSame('wrong_activity', Json::str($problem, 'context.situation'));
    }

    public function test_a_missing_confirmation_is_escalated_and_attention_hears_of_it(): void
    {
        $paid = $this->paidParticipant();
        $this->setParticipationColumn($paid['participationId'], 'legal_state', 'incomplete');
        $this->login(self::OWNER);

        $result = $this->checkIn(['participationId' => $paid['participationId']]);

        self::assertResponseStatusCodeSame(202);
        self::assertSame('escalated', Json::str($result, 'outcome'));
        self::assertSame('missing_confirmation', Json::str($result, 'situation'));
        self::assertSame(0, $this->validCheckIns($paid['participationId']), 'the participant stays outside');

        $this->drain();
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE subject_type = 'participation' AND subject_id = ?", [$paid['participationId']]));
    }

    public function test_the_operator_can_pass_a_situation_on(): void
    {
        $jana = $this->participant();
        $this->setParticipationColumn($jana['participationId'], 'state', 'cancelled');

        $result = $this->post($this->teamPath('/lists/'.$this->trailList.'/escalations'), ['participationId' => $jana['participationId']]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('cancelled_or_expired', Json::str($result, 'situation'));
        $this->drain();
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM attention.attention_item WHERE subject_type = 'participation' AND subject_id = ?", [$jana['participationId']]));
    }

    public function test_there_is_nothing_to_escalate_for_a_participant_who_may_check_in(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);

        $this->post($this->teamPath('/lists/'.$this->trailList.'/escalations'), ['participationId' => $paid['participationId']]);

        $this->assertProblem(409, 'nothing_to_escalate');
    }

    public function test_the_idempotency_key_is_mandatory(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);

        $this->request('POST', $this->teamPath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $paid['participationId']]);

        $this->assertProblem(400, 'idempotency_key_required');
    }

    public function test_the_same_key_for_another_participant_is_a_conflict(): void
    {
        $alena = $this->paidParticipant('Alena');
        $bedrich = $this->paidParticipant('Bedřich');
        $this->login(self::OWNER);
        $key = $this->key();
        $this->checkIn(['participationId' => $alena['participationId']], key: $key);

        $this->checkIn(['participationId' => $bedrich['participationId']], key: $key);

        $this->assertProblem(409, 'idempotency_key_reused');
    }

    public function test_the_body_is_validated(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);

        $this->checkIn(['participationId' => $paid['participationId'], 'stationId' => 'not-a-uuid']);
        $this->assertProblem(422, 'validation_failed');

        $this->checkIn([]);
        $this->assertProblem(422, 'validation_failed');

        $this->checkIn(['qr' => $this->qrOf($paid['participationId']), 'code' => $paid['code']]);
        $this->assertProblem(422, 'validation_failed');
    }

    public function test_a_forged_qr_token_is_refused(): void
    {
        $this->checkIn(['qr' => 'qr1.forged.token']);

        $this->assertProblem(422, 'qr_token_invalid');
    }

    public function test_an_outdated_qr_token_is_refused_after_the_version_moved_on(): void
    {
        $paid = $this->paidParticipant();
        $token = $this->qrOf($paid['participationId']);
        $this->connection()->executeStatement('UPDATE registration.participation SET qr_version = qr_version + 1 WHERE id = ?', [$paid['participationId']]);
        $this->login(self::OWNER);

        $this->checkIn(['qr' => $token]);

        $this->assertProblem(422, 'qr_token_invalid');
        self::assertSame(0, $this->validCheckIns($paid['participationId']));
    }

    public function test_unknown_and_foreign_lists_and_participations_are_not_found(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);

        $this->checkIn(['participationId' => $paid['participationId']], '0199a000-0000-7000-8000-0000000000bb');
        $this->assertProblem(404, 'check_in_list_not_found');

        $this->checkIn(['participationId' => '0199a000-0000-7000-8000-0000000000cc']);
        $this->assertProblem(404, 'participation_not_found');

        $this->post($this->teamPath('/lists/'.$this->trailList.'/check-ins', eventId: '0199a000-0000-7000-8000-0000000000aa'), ['participationId' => $paid['participationId']]);
        $this->assertProblem(404, 'check_in_list_not_found');
    }

    public function test_anonymous_is_unauthorized_and_a_foreign_organizer_gets_not_found(): void
    {
        $paid = $this->paidParticipant();
        $this->client->getCookieJar()->clear();

        $this->checkIn(['participationId' => $paid['participationId']]);
        $this->assertProblem(401, 'unauthorized');

        $this->signInAsForeignOwner();
        $this->checkIn(['participationId' => $paid['participationId']]);
        $this->assertProblem(404, 'not_found');
        self::assertSame(0, $this->validCheckIns($paid['participationId']));
    }

    public function test_a_role_without_the_check_in_permission_is_forbidden(): void
    {
        $paid = $this->paidParticipant();
        $this->signInAs(Role::Finance, 'finance@example.test');

        $this->checkIn(['participationId' => $paid['participationId']]);

        $this->assertProblem(403, 'forbidden');
    }

    public function test_a_check_in_operator_checks_in(): void
    {
        $paid = $this->paidParticipant();
        $this->signInAs(Role::CheckIn, 'door@example.test');

        $this->checkIn(['participationId' => $paid['participationId']]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_team_session_without_the_csrf_header_is_refused(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);

        $this->request('POST', $this->teamPath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $paid['participationId']], ['Idempotency-Key' => $this->key()], csrf: false);

        $this->assertProblem(403, 'csrf_invalid');
        self::assertSame(0, $this->validCheckIns($paid['participationId']));
    }

    public function test_a_device_token_checks_in_without_a_session_and_is_named_in_the_answer(): void
    {
        $paid = $this->paidParticipant();
        $token = $this->issueDeviceToken($this->trailList, 'Gate A tablet')['token'];

        $result = $this->post($this->devicePath('/lists/'.$this->trailList.'/check-ins'), ['qr' => $this->qrOf($paid['participationId'])], self::bearer($token));

        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertSame('checked_in', Json::str($result, 'outcome'));
        $row = $this->dbRow('SELECT checked_in_by, device_label FROM checkin.check_in WHERE participation_id = ?', [$paid['participationId']]);
        self::assertNull($row['checked_in_by']);
        self::assertSame('Gate A tablet', $row['device_label']);

        $this->post($this->devicePath('/lists/'.$this->trailList.'/check-ins'), ['qr' => $this->qrOf($paid['participationId'])], self::bearer($token));
        $problem = $this->assertProblem(409, 'already_checked_in');
        self::assertSame('Gate A tablet', Json::str($problem, 'context.deviceLabel'));
    }

    public function test_a_device_needs_no_csrf_header(): void
    {
        $paid = $this->paidParticipant();
        $token = $this->issueDeviceToken()['token'];

        $this->request('POST', $this->devicePath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $paid['participationId']], self::bearer($token) + ['Idempotency-Key' => $this->key()], csrf: false);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_device_bound_to_another_list_is_forbidden(): void
    {
        $paid = $this->paidParticipant();
        $token = $this->issueDeviceToken($this->kidsList)['token'];

        $this->post($this->devicePath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $paid['participationId']], self::bearer($token));

        $this->assertProblem(403, 'forbidden');
        self::assertSame(0, $this->validCheckIns($paid['participationId']));
    }

    public function test_a_revoked_device_token_is_unauthorized(): void
    {
        $paid = $this->paidParticipant();
        $issued = $this->issueDeviceToken();
        $this->login(self::OWNER);
        $this->request('DELETE', self::BASE.'/device-tokens/'.$issued['id']);
        self::assertResponseStatusCodeSame(204);
        $this->client->getCookieJar()->clear();

        $this->post($this->devicePath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $paid['participationId']], self::bearer($issued['token']));

        $this->assertProblem(401, 'invalid_device_token');
    }

    public function test_a_cancelled_event_closes_the_lists(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);
        $this->connection()->executeStatement('UPDATE checkin.check_in_list SET cancelled_at = now() WHERE id = ?', [$this->trailList]);

        $this->checkIn(['participationId' => $paid['participationId']]);

        $this->assertProblem(409, 'check_in_list_closed');
    }
}
