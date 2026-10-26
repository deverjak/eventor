<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\CheckIn;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\CheckIn\CheckInHttpTestCase;

/**
 * CHK-08 over HTTP: cash taken at the desk and the check-in in one transaction, for a team member and for a device
 * (which needs the organizer policy `check_in_may_record_cash`).
 */
final class CashCheckInTest extends CheckInHttpTestCase
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
    private function cash(array $body, ?string $list = null, array $headers = [], ?string $key = null): ?array
    {
        return $this->post($this->teamPath('/lists/'.($list ?? $this->trailList).'/cash-check-ins'), $body, $headers, $key);
    }

    public function test_cash_and_check_in_happen_together(): void
    {
        $jana = $this->participant();

        $result = $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 30000, 'note' => 'At the gate']);

        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertSame('paid', Json::str($result, 'financialState'));
        self::assertSame(30000, Json::int($result, 'received'));
        self::assertSame(30000, Json::int($result, 'amountDue'));
        self::assertFalse(Json::at($result, 'alreadyRecorded'));

        $row = $this->dbRow('SELECT * FROM checkin.check_in WHERE participation_id = ?', [$jana['participationId']]);
        self::assertSame(Json::str($result, 'checkInId'), $row['id']);
        self::assertSame(30000, (int) self::scalar($row['cash_minor']));
        self::assertSame(Json::str($result, 'ledgerEntryId'), $row['ledger_entry_id']);
        self::assertSame('paid', $this->dbString('SELECT state FROM payments.order_finance WHERE order_id = ?', [$jana['orderId']]));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM payments.ledger_entry WHERE order_id = ? AND kind = 'cash'", [$jana['orderId']]));
    }

    public function test_a_repeated_request_records_the_money_once(): void
    {
        $jana = $this->participant();
        $key = $this->key();

        $first = $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 30000], key: $key);
        $again = $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 30000], key: $key);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(Json::str($first, 'ledgerEntryId'), Json::str($again, 'ledgerEntryId'));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM payments.ledger_entry WHERE order_id = ? AND kind = 'cash'", [$jana['orderId']]));
        self::assertSame(1, $this->validCheckIns($jana['participationId']));
    }

    public function test_a_failing_check_in_leaves_no_cash_behind(): void
    {
        $jana = $this->participant();
        $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 30000]);

        // Checked in already: the second request is refused before any money moves.
        $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 500]);

        $this->assertProblem(409, 'already_checked_in');
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM payments.ledger_entry WHERE order_id = ? AND kind = 'cash'", [$jana['orderId']]));
    }

    public function test_a_paid_participant_has_nothing_to_pay(): void
    {
        $paid = $this->paidParticipant();
        $this->login(self::OWNER);

        $this->cash(['participationId' => $paid['participationId'], 'amountMinor' => 1000]);

        $this->assertProblem(409, 'cash_check_in_not_applicable');
        self::assertSame(0, $this->validCheckIns($paid['participationId']));
    }

    public function test_cash_does_not_settle_a_missing_confirmation(): void
    {
        $jana = $this->participant();
        $this->setParticipationColumn($jana['participationId'], 'legal_state', 'incomplete');

        $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 30000]);

        $problem = $this->assertProblem(409, 'cash_check_in_not_applicable');
        self::assertSame('missing_confirmation', Json::str($problem, 'context.situation'));
        self::assertSame(0, $this->dbInt("SELECT count(*) FROM payments.ledger_entry WHERE order_id = ? AND kind = 'cash'", [$jana['orderId']]));
    }

    public function test_a_cancelled_participation_is_blocked(): void
    {
        $jana = $this->participant();
        $this->setParticipationColumn($jana['participationId'], 'state', 'cancelled');

        $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 30000]);

        $this->assertProblem(409, 'check_in_blocked');
    }

    public function test_the_amount_is_validated(): void
    {
        $jana = $this->participant();

        $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 0]);
        $this->assertProblem(422, 'validation_failed');

        $this->cash(['participationId' => $jana['participationId']]);
        $this->assertProblem(422, 'validation_failed');

        $this->cash(['amountMinor' => 100]);
        $this->assertProblem(422, 'validation_failed');
    }

    public function test_the_idempotency_key_is_mandatory(): void
    {
        $jana = $this->participant();

        $this->request('POST', $this->teamPath('/lists/'.$this->trailList.'/cash-check-ins'), ['participationId' => $jana['participationId'], 'amountMinor' => 30000]);

        $this->assertProblem(400, 'idempotency_key_required');
    }

    public function test_anonymous_and_foreign_organizer_and_unknown_list(): void
    {
        $jana = $this->participant();
        $body = ['participationId' => $jana['participationId'], 'amountMinor' => 30000];

        $this->cash($body, '0199a000-0000-7000-8000-0000000000bb');
        $this->assertProblem(404, 'check_in_list_not_found');

        $this->client->getCookieJar()->clear();
        $this->cash($body);
        $this->assertProblem(401, 'unauthorized');

        $this->signInAsForeignOwner();
        $this->cash($body);
        $this->assertProblem(404, 'not_found');
    }

    public function test_a_check_in_operator_takes_cash_only_when_the_policy_allows_it(): void
    {
        $jana = $this->participant();
        $this->signInAs(Role::CheckIn, 'door@example.test');

        $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 30000]);
        $this->assertProblem(403, 'forbidden');

        $this->allowDevicesToTakeCash();
        $this->client->getCookieJar()->clear();
        $this->login('door@example.test');
        $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 30000]);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
    }

    public function test_a_finance_clerk_takes_cash_but_does_not_check_people_in(): void
    {
        $jana = $this->participant();
        $this->signInAs(Role::Finance, 'finance@example.test');

        $this->cash(['participationId' => $jana['participationId'], 'amountMinor' => 30000]);

        $this->assertProblem(403, 'forbidden');
        self::assertSame(0, $this->dbInt("SELECT count(*) FROM payments.ledger_entry WHERE order_id = ? AND kind = 'cash'", [$jana['orderId']]));
    }

    public function test_a_device_cannot_take_cash_unless_the_organizer_allows_it(): void
    {
        $jana = $this->participant();
        $token = $this->issueDeviceToken()['token'];

        $this->post($this->devicePath('/lists/'.$this->trailList.'/cash-check-ins'), ['participationId' => $jana['participationId'], 'amountMinor' => 30000], self::bearer($token));

        $this->assertProblem(403, 'forbidden');
        self::assertSame(0, $this->validCheckIns($jana['participationId']));
    }

    public function test_a_device_takes_cash_when_the_organizer_allows_it(): void
    {
        $jana = $this->participant();
        $this->allowDevicesToTakeCash();
        $token = $this->issueDeviceToken($this->trailList, 'Gate B')['token'];

        $result = $this->post($this->devicePath('/lists/'.$this->trailList.'/cash-check-ins'), ['qr' => $this->qrOf($jana['participationId']), 'amountMinor' => 30000], self::bearer($token));

        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertSame('paid', Json::str($result, 'financialState'));
        $row = $this->dbRow('SELECT checked_in_by, device_label, cash_minor FROM checkin.check_in WHERE participation_id = ?', [$jana['participationId']]);
        self::assertSame('Gate B', $row['device_label']);
        self::assertSame(30000, (int) self::scalar($row['cash_minor']));
        $ledger = $this->dbRow("SELECT actor FROM payments.ledger_entry WHERE order_id = ? AND kind = 'cash'", [$jana['orderId']]);
        self::assertSame('SYSTEM', Json::str(json_decode((string) self::scalar($ledger['actor']), true, 512, \JSON_THROW_ON_ERROR), 'type'));
    }

    private static function scalar(mixed $value): int|string
    {
        self::assertTrue(\is_int($value) || \is_string($value));

        return $value;
    }
}
