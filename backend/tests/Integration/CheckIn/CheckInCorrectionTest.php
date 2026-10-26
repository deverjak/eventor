<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\CheckIn;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\CheckIn\CheckInHttpTestCase;

/**
 * CHK-07 over HTTP: a manager voids or corrects a check-in; audited with the reason; devices and operators cannot.
 */
final class CheckInCorrectionTest extends CheckInHttpTestCase
{
    private string $participation;

    private string $checkIn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openDesk();
        $paid = $this->paidParticipant();
        $this->participation = $paid['participationId'];
        $this->login(self::OWNER);
        $result = $this->post($this->teamPath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $this->participation]);
        $this->checkIn = Json::str($result, 'checkInId');
    }

    private function void(string $reason = 'Scanned the wrong ticket', ?string $checkIn = null): void
    {
        $this->request('POST', $this->teamPath('/check-ins/'.($checkIn ?? $this->checkIn).'/void'), ['reason' => $reason]);
    }

    public function test_voiding_frees_the_participant_to_check_in_again(): void
    {
        $this->void();

        self::assertResponseStatusCodeSame(204);
        $row = $this->dbRow('SELECT voided_at, voided_by, void_reason FROM checkin.check_in WHERE id = ?', [$this->checkIn]);
        self::assertNotNull($row['voided_at']);
        self::assertSame($this->owner['userAccountId'], $row['voided_by']);
        self::assertSame('Scanned the wrong ticket', $row['void_reason']);
        self::assertSame(0, $this->validCheckIns($this->participation));

        $again = $this->post($this->teamPath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $this->participation]);
        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($this->checkIn, Json::str($again, 'checkInId'));
        self::assertSame(2, $this->dbInt('SELECT count(*) FROM checkin.check_in WHERE participation_id = ?', [$this->participation]), 'the voided row stays');
    }

    public function test_a_void_is_audited_with_the_reason(): void
    {
        $this->void('Duplicate scan by mistake');

        $audit = $this->dbRow("SELECT reason, actor_type FROM audit.audit_entry WHERE action = 'check_in.voided' AND entity_id = ?", [$this->checkIn]);
        self::assertSame('Duplicate scan by mistake', $audit['reason']);
        self::assertSame('USER', $audit['actor_type']);
    }

    public function test_a_void_needs_a_reason(): void
    {
        $this->request('POST', $this->teamPath('/check-ins/'.$this->checkIn.'/void'), ['reason' => '']);
        $this->assertProblem(422, 'validation_failed');

        $this->request('POST', $this->teamPath('/check-ins/'.$this->checkIn.'/void'), []);
        $this->assertProblem(422, 'validation_failed');

        self::assertSame(1, $this->validCheckIns($this->participation));
    }

    public function test_a_voided_row_cannot_be_voided_again(): void
    {
        $this->void();

        $this->void();

        $this->assertProblem(409, 'check_in_already_voided');
    }

    public function test_an_unknown_check_in_and_one_of_another_event_are_not_found(): void
    {
        $this->void(checkIn: '0199a000-0000-7000-8000-0000000000ff');
        $this->assertProblem(404, 'check_in_not_found');

        $this->request('POST', $this->teamPath('/check-ins/'.$this->checkIn.'/void', eventId: '0199a000-0000-7000-8000-0000000000aa'), ['reason' => 'Mistake']);
        $this->assertProblem(404, 'check_in_not_found');
        self::assertSame(1, $this->validCheckIns($this->participation));
    }

    public function test_correcting_replaces_the_row_with_the_corrected_station_and_time(): void
    {
        $station = '0199a000-0000-7000-8000-0000000000ee';

        $result = $this->request('POST', $this->teamPath('/check-ins/'.$this->checkIn.'/correct'), [
            'reason' => 'Arrived earlier at the second gate',
            'stationId' => $station,
            'checkedInAt' => '2026-11-21T09:05:00+01:00',
        ]);

        self::assertResponseStatusCodeSame(200, (string) $this->client->getResponse()->getContent());
        self::assertSame($this->checkIn, Json::str($result, 'replacedCheckInId'));
        $new = $this->dbRow('SELECT * FROM checkin.check_in WHERE id = ?', [Json::str($result, 'checkInId')]);
        self::assertSame($this->checkIn, $new['corrects_id']);
        self::assertSame($station, $new['station_id']);
        self::assertNull($new['voided_at']);
        self::assertSame('2026-11-21 08:05:00+00', $new['checked_in_at']);
        self::assertSame(1, $this->validCheckIns($this->participation), 'exactly one valid row after a correction');
        self::assertNotNull($this->dbRow('SELECT voided_at FROM checkin.check_in WHERE id = ?', [$this->checkIn])['voided_at']);
        $audit = $this->dbRow("SELECT reason FROM audit.audit_entry WHERE action = 'check_in.corrected' AND entity_id = ?", [$this->checkIn]);
        self::assertSame('Arrived earlier at the second gate', $audit['reason']);
    }

    public function test_a_correction_without_changes_keeps_station_and_time(): void
    {
        $before = $this->dbRow('SELECT station_id, checked_in_at FROM checkin.check_in WHERE id = ?', [$this->checkIn]);

        $result = $this->request('POST', $this->teamPath('/check-ins/'.$this->checkIn.'/correct'), ['reason' => 'Re-recorded']);

        $after = $this->dbRow('SELECT station_id, checked_in_at FROM checkin.check_in WHERE id = ?', [Json::str($result, 'checkInId')]);
        self::assertSame($before, $after);
    }

    public function test_a_correction_is_validated_and_a_voided_row_cannot_be_corrected(): void
    {
        $this->request('POST', $this->teamPath('/check-ins/'.$this->checkIn.'/correct'), ['reason' => '']);
        $this->assertProblem(422, 'validation_failed');

        $this->request('POST', $this->teamPath('/check-ins/'.$this->checkIn.'/correct'), ['reason' => 'x', 'stationId' => 'nope']);
        $this->assertProblem(422, 'validation_failed');

        $this->void();
        $this->request('POST', $this->teamPath('/check-ins/'.$this->checkIn.'/correct'), ['reason' => 'Too late']);
        $this->assertProblem(409, 'check_in_already_voided');
    }

    public function test_anonymous_and_foreign_organizer(): void
    {
        $this->client->getCookieJar()->clear();
        $this->void();
        $this->assertProblem(401, 'unauthorized');

        $this->signInAsForeignOwner();
        $this->void();
        $this->assertProblem(404, 'not_found');
        self::assertSame(1, $this->validCheckIns($this->participation));
    }

    public function test_only_a_manager_may_correct_not_the_operator_or_finance(): void
    {
        $this->signInAs(Role::CheckIn, 'door@example.test');
        $this->void();
        $this->assertProblem(403, 'forbidden');

        $this->signInAs(Role::Finance, 'finance@example.test');
        $this->void();
        $this->assertProblem(403, 'forbidden');

        $this->signInAs(Role::EventManager, 'manager@example.test');
        $this->void();
        self::assertResponseStatusCodeSame(204);
    }

    public function test_a_device_token_cannot_void(): void
    {
        $token = $this->issueDeviceToken()['token'];

        $this->request('POST', $this->teamPath('/check-ins/'.$this->checkIn.'/void'), ['reason' => 'Mistake'], self::bearer($token));

        $this->assertProblem(403, 'forbidden');
        self::assertSame(1, $this->validCheckIns($this->participation));
    }
}
