<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Payments;

use Eventor\Payments\Application\ChangeOrderAmountDue\ChangeOrderAmountDue;
use Eventor\Payments\Application\CorrectCashEntry\CorrectCashEntry;
use Eventor\Payments\Application\RecordCash\RecordCash;
use Eventor\Payments\Application\RecordManualPayment\RecordManualPayment;
use Eventor\Payments\Contract\CashDesk;
use Eventor\Payments\Contract\Exception\OrderFinanceNotFound;
use Eventor\Payments\Contract\FinanceQuery;
use Eventor\Shared\Application\Idempotency\IdempotencyKeyReused;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Eventor\Tests\Support\IntegrationTestCase;
use Eventor\Tests\Support\Payments\PaymentsFixtures;

/**
 * Ledger and money core: finance row, cash (S11 included), manual corrections, amount changes, tenancy.
 */
final class LedgerCoreTest extends IntegrationTestCase
{
    use PaymentsFixtures;

    private OrganizerId $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = OrganizerId::fromString($this->createOwner('fin@example.test', 'fin-club')['organizerId']);
    }

    public function test_the_initializer_creates_an_unpaid_row_and_is_idempotent(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000);
        $this->placeOrderFinance($this->org, 99_999, order: $order);

        $view = $this->service(FinanceQuery::class)->forOrder($this->org, $order);
        self::assertSame(FinancialState::Unpaid, $view->summary->state);
        self::assertSame(30_000, $view->summary->amountDue->minor, 'the second call changed nothing');
        self::assertTrue($view->instructionsPending, 'a transfer order waits for its payment request');
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM payments.order_finance WHERE order_id = ?', [$order->toString()]));
    }

    public function test_free_and_cash_orders(): void
    {
        $free = $this->placeOrderFinance($this->org, 0, PaymentMethod::Free);
        $cash = $this->placeOrderFinance($this->org, 20_000, PaymentMethod::Cash);

        $query = $this->service(FinanceQuery::class);
        self::assertSame(FinancialState::NotRequired, $query->forOrder($this->org, $free)->summary->state);
        $view = $query->forOrder($this->org, $cash);
        self::assertSame(FinancialState::Unpaid, $view->summary->state);
        self::assertFalse($view->instructionsPending, 'cash needs no payment request');
    }

    public function test_cash_pays_the_order_emits_events_and_is_idempotent(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);

        $first = $this->dispatchAs($this->org, new RecordCash($order->toString(), 30_000, 'cash-key-0000000001'));
        self::assertIsArray($first);
        self::assertSame('paid', $first['state']);
        self::assertFalse($first['alreadyRecorded']);

        $again = $this->dispatchAs($this->org, new RecordCash($order->toString(), 30_000, 'cash-key-0000000001'));
        self::assertIsArray($again);
        self::assertTrue($again['alreadyRecorded']);
        self::assertSame($first['ledgerEntryId'], $again['ledgerEntryId']);

        self::assertSame(1, $this->dbInt('SELECT count(*) FROM payments.ledger_entry WHERE order_id = ?', [$order->toString()]));
        $row = $this->dbRow('SELECT state, received_minor, received_cash_minor, ledger_version FROM payments.order_finance WHERE order_id = ?', [$order->toString()]);
        self::assertSame('paid', $row['state']);
        self::assertSame(30_000, self::n($row['received_cash_minor']));
        self::assertSame(2, self::n($row['ledger_version']), 'one rewrite after the initial version');

        // outbox: payments.order_payment_changed (registration, communication, reporting subscribers pending) and cash_recorded
        $this->assertAudited('ledger.cash');
    }

    public function test_the_same_cash_key_with_another_amount_is_refused(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $this->dispatchAs($this->org, new RecordCash($order->toString(), 10_000, 'cash-key-0000000002'));

        $this->expectException(IdempotencyKeyReused::class);
        $this->dispatchAs($this->org, new RecordCash($order->toString(), 20_000, 'cash-key-0000000002'));
    }

    public function test_the_cash_facade_records_in_the_callers_transaction(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $this->enterTenant($this->org);

        $desk = $this->service(CashDesk::class);
        $receipt = $this->entityManager()->wrapInTransaction(fn() => $desk->recordCash($this->org, $order, Money::of(30_000), null, 'desk-key-000000000001', 'at the door'));
        $this->entityManager()->flush();

        self::assertSame(FinancialState::Paid, $receipt->finance->state);
        self::assertFalse($receipt->alreadyRecorded);
        $repeat = $this->entityManager()->wrapInTransaction(fn() => $desk->recordCash($this->org, $order, Money::of(30_000), null, 'desk-key-000000000001'));
        self::assertTrue($repeat->alreadyRecorded);
        self::assertTrue($repeat->ledgerEntryId->equals($receipt->ledgerEntryId));
    }

    public function test_s11_a_cash_correction_compensates_and_may_replace_the_amount(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $cash = $this->dispatchAs($this->org, new RecordCash($order->toString(), 50_000, 'cash-key-0000000003'));
        self::assertIsArray($cash);
        self::assertSame('overpaid', $cash['state']);

        $fixed = $this->dispatchAs($this->org, new CorrectCashEntry($order->toString(), self::t($cash['ledgerEntryId']), 'typed 500 instead of 300', 'fix-key-00000000000001', 30_000));
        self::assertIsArray($fixed);
        self::assertSame('paid', $fixed['state']);
        self::assertNotNull($fixed['replacementId']);

        $kinds = $this->dbColumn('SELECT kind FROM payments.ledger_entry WHERE order_id = ? ORDER BY recorded_at, kind', [$order->toString()]);
        self::assertEqualsCanonicalizing(['cash', 'cash_correction', 'cash'], $kinds, 'nothing was updated or deleted');
        self::assertSame(
            $cash['ledgerEntryId'],
            $this->dbString("SELECT compensates_id FROM payments.ledger_entry WHERE kind = 'cash_correction' AND order_id = ?", [$order->toString()]),
        );
        self::assertSame('typed 500 instead of 300', $this->dbString("SELECT reason FROM payments.ledger_entry WHERE kind = 'cash_correction' AND order_id = ?", [$order->toString()]));
        $this->assertAudited('ledger.cash_correction');

        // A repeated correction request returns the first result.
        $again = $this->dispatchAs($this->org, new CorrectCashEntry($order->toString(), self::t($cash['ledgerEntryId']), 'typed 500 instead of 300', 'fix-key-00000000000001', 30_000));
        self::assertIsArray($again);
        self::assertTrue($again['alreadyRecorded']);
        self::assertSame(3, $this->dbInt('SELECT count(*) FROM payments.ledger_entry WHERE order_id = ?', [$order->toString()]));
    }

    public function test_a_cash_entry_cannot_be_corrected_twice(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $cash = $this->dispatchAs($this->org, new RecordCash($order->toString(), 30_000, 'cash-key-0000000004'));
        self::assertIsArray($cash);
        $this->dispatchAs($this->org, new CorrectCashEntry($order->toString(), self::t($cash['ledgerEntryId']), 'wrong order', 'fix-key-00000000000002'));

        $this->expectException(\Eventor\Payments\Domain\Ledger\NotCorrectable::class);
        $this->dispatchAs($this->org, new CorrectCashEntry($order->toString(), self::t($cash['ledgerEntryId']), 'and again', 'fix-key-00000000000003'));
    }

    public function test_the_ledger_is_append_only_for_the_application_role(): void
    {
        foreach (['UPDATE', 'DELETE', 'TRUNCATE'] as $privilege) {
            self::assertFalse((bool) $this->connection()->fetchOne("SELECT has_table_privilege('eventor_app', 'payments.ledger_entry', ?)", [$privilege]), $privilege);
        }
        foreach (['INSERT', 'SELECT'] as $privilege) {
            self::assertTrue((bool) $this->connection()->fetchOne("SELECT has_table_privilege('eventor_app', 'payments.ledger_entry', ?)", [$privilege]), $privilege);
        }
    }

    public function test_a_manual_correction_books_a_payment_outside_pairrot_with_a_reason(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000);

        $result = $this->dispatchAs($this->org, new RecordManualPayment($order->toString(), 30_000, 'paid by voucher', 'man-key-00000000000001'));
        self::assertIsArray($result);
        self::assertSame('paid', $result['state']);
        self::assertSame('paid by voucher', $this->dbString("SELECT reason FROM payments.ledger_entry WHERE kind = 'manual_correction'"));
    }

    public function test_an_amount_change_recomputes_the_state_and_flags_the_excess(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $this->dispatchAs($this->org, new RecordCash($order->toString(), 30_000, 'cash-key-0000000006'));

        $up = $this->dispatchAs($this->org, new ChangeOrderAmountDue($order->toString(), 40_000, 'variant_changed'));
        self::assertIsArray($up);
        self::assertSame('partially_paid', $up['state']);

        $down = $this->dispatchAs($this->org, new ChangeOrderAmountDue($order->toString(), 0, 'event_cancelled'));
        self::assertIsArray($down);
        self::assertSame('overpaid', $down['state'], 'cancelled after payment: the whole amount is an excess');

        $same = $this->dispatchAs($this->org, new ChangeOrderAmountDue($order->toString(), 0, 'event_cancelled'));
        self::assertIsArray($same);
        self::assertFalse($same['changed']);
    }

    public function test_an_order_of_another_organizer_is_not_found(): void
    {
        $other = OrganizerId::fromString($this->createOwner('other@example.test', 'other-club')['organizerId']);
        $order = $this->placeOrderFinance($other, 30_000, PaymentMethod::Cash);

        $this->expectException(OrderFinanceNotFound::class);
        $this->dispatchAs($this->org, new RecordCash($order->toString(), 30_000, 'cash-key-0000000007'));
    }

    public function test_an_unknown_order_is_not_found(): void
    {
        $this->expectException(OrderFinanceNotFound::class);
        $this->dispatchAs($this->org, new RecordCash(OrderId::generate()->toString(), 100, 'cash-key-0000000008'));
    }

    private function assertAudited(string $action): void
    {
        self::assertGreaterThan(0, $this->dbInt('SELECT count(*) FROM audit.audit_entry WHERE action = ?', [$action]), "audit entry $action");
    }
}
