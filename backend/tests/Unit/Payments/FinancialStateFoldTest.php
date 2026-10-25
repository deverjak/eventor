<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Payments;

use Eventor\Payments\Domain\Ledger\FinancialStateFold;
use Eventor\Payments\Domain\Ledger\LedgerEntry;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The fold of chapter 05, case by case: the financial state is a pure function of the ledger and the amount due.
 */
final class FinancialStateFoldTest extends TestCase
{
    private OrganizerId $org;
    private OrderId $order;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->org = OrganizerId::generate();
        $this->order = OrderId::generate();
        $this->now = new \DateTimeImmutable('2026-11-01T12:00:00+00:00');
    }

    /**
     * @param list<array{0: string, 1: int}> $entries kind and amount
     */
    #[DataProvider('cases')]
    public function test_fold(array $entries, int $due, bool $openRefund, bool $disputed, FinancialState $expected, int $received): void
    {
        $fold = FinancialStateFold::fold($this->build($entries), Money::of($due), $openRefund, $disputed);

        self::assertSame($expected, $fold->state);
        self::assertSame($received, $fold->received->minor);
        self::assertSame($due, $fold->amountDue->minor);
    }

    /**
     * @return iterable<string, array{list<array{0: string, 1: int}>, int, bool, bool, FinancialState, int}>
     */
    public static function cases(): iterable
    {
        yield 'new order' => [[], 30_000, false, false, FinancialState::Unpaid, 0];
        yield 'free order' => [[], 0, false, false, FinancialState::NotRequired, 0];
        yield 'partial transfer (S04)' => [[['alloc', 10_000]], 30_000, false, false, FinancialState::PartiallyPaid, 10_000];
        yield 'exact transfer (S03)' => [[['alloc', 30_000]], 30_000, false, false, FinancialState::Paid, 30_000];
        yield 'two transfers add up' => [[['alloc', 10_000], ['alloc', 20_000]], 30_000, false, false, FinancialState::Paid, 30_000];
        yield 'overpaid (S07)' => [[['alloc', 35_000]], 30_000, false, false, FinancialState::Overpaid, 35_000];
        yield 'overpaid with an open refund task' => [[['alloc', 35_000]], 30_000, true, false, FinancialState::RefundPending, 35_000];
        yield 'cash pays the order' => [[['cash', 30_000]], 30_000, false, false, FinancialState::Paid, 30_000];
        yield 'cash correction compensates (S11)' => [[['cash', 30_000], ['cash_correction', -30_000]], 30_000, false, false, FinancialState::Unpaid, 0];
        yield 'cash correction with replacement (S11)' => [[['cash', 50_000], ['cash_correction', -50_000], ['cash', 30_000]], 30_000, false, false, FinancialState::Paid, 30_000];
        yield 'revocation reopens the order' => [[['alloc', 30_000], ['revoke', -30_000]], 30_000, false, false, FinancialState::Unpaid, 0];
        yield 'partial revocation' => [[['alloc', 30_000], ['revoke', -10_000]], 30_000, false, false, FinancialState::PartiallyPaid, 20_000];
        yield 'manual correction books a foreign payment' => [[['manual', 30_000]], 30_000, false, false, FinancialState::Paid, 30_000];
        yield 'transfer plus cash' => [[['alloc', 10_000], ['cash', 20_000]], 30_000, false, false, FinancialState::Paid, 30_000];
        yield 'disputed wins over paid' => [[['alloc', 30_000]], 30_000, false, true, FinancialState::Disputed, 30_000];
        yield 'disputed wins over unpaid' => [[], 30_000, false, true, FinancialState::Disputed, 0];
        yield 'cancelled before payment' => [[], 0, false, false, FinancialState::NotRequired, 0];
        yield 'cancelled after payment: refund due' => [[['alloc', 30_000]], 0, false, false, FinancialState::Overpaid, 30_000];
        yield 'cancelled after payment, task open' => [[['alloc', 30_000]], 0, true, false, FinancialState::RefundPending, 30_000];
        yield 'refund settled after cancellation' => [[['alloc', 30_000], ['refund', -30_000]], 0, false, false, FinancialState::Refunded, 0];
        yield 'overpayment refunded' => [[['alloc', 35_000], ['refund', -5_000]], 30_000, false, false, FinancialState::Paid, 30_000];
        yield 'price rose after full payment' => [[['alloc', 30_000]], 40_000, false, false, FinancialState::PartiallyPaid, 30_000];
        yield 'price fell after full payment' => [[['alloc', 30_000]], 20_000, false, false, FinancialState::Overpaid, 30_000];
        yield 'everything refunded while still owed' => [[['cash', 30_000], ['refund', -30_000]], 30_000, false, false, FinancialState::Unpaid, 0];
        yield 'more refunded than received' => [[['cash', 10_000], ['refund', -20_000]], 0, false, false, FinancialState::Refunded, -10_000];
        yield 'negative balance never looks paid' => [[['alloc', 10_000], ['revoke', -20_000]], 30_000, false, false, FinancialState::Unpaid, -10_000];
    }

    public function test_the_pairrot_and_cash_split_for_pay_13(): void
    {
        $fold = FinancialStateFold::fold($this->build([['alloc', 10_000], ['cash', 20_000], ['cash_correction', -20_000], ['cash', 5_000], ['manual', 1_000], ['refund', -2_000]]), Money::of(30_000));

        self::assertSame(11_000, $fold->receivedTransfer->minor, 'Pairrot plus manual bookings');
        self::assertSame(5_000, $fold->receivedCash->minor, 'cash net of corrections');
        self::assertSame(2_000, $fold->refunded->minor, 'refunded is a positive amount');
        self::assertSame(14_000, $fold->received->minor);
    }

    public function test_overpaid_is_the_excess_over_the_amount_due_and_never_negative(): void
    {
        self::assertSame(5_000, FinancialStateFold::fold($this->build([['alloc', 35_000]]), Money::of(30_000))->overpaid()->minor);
        self::assertSame(0, FinancialStateFold::fold($this->build([['alloc', 20_000]]), Money::of(30_000))->overpaid()->minor);
    }

    /**
     * @param list<array{0: string, 1: int}> $entries
     *
     * @return list<LedgerEntry>
     */
    private function build(array $entries): array
    {
        $actor = AuditActor::system('test');
        $request = PaymentRequestId::generate();
        $cashEntry = null;
        $built = [];
        foreach ($entries as $i => [$kind, $amount]) {
            $money = Money::of($amount);
            $entry = match ($kind) {
                'alloc', 'revoke' => LedgerEntry::pairrotDelta($this->org, $this->order, $request, $money, 'evt-'.$i, null, $this->now, $this->now),
                'cash' => $cashEntry = LedgerEntry::cash($this->org, $this->order, $money, null, 'key-'.$i, $actor, null, $this->now),
                'cash_correction' => LedgerEntry::cashCorrection($cashEntry ?? throw new \LogicException('no cash to correct'), 'fix-'.$i, $actor, 'typo', $this->now),
                'manual' => LedgerEntry::manualCorrection($this->org, $this->order, $money, 'man-'.$i, $actor, 'voucher', $this->now),
                'refund' => LedgerEntry::refundSettled($this->org, $this->order, $money->negate(), 'task-'.$i, $actor, null, $this->now),
                default => throw new \LogicException($kind),
            };
            if ('cash_correction' === $kind) {
                self::assertSame($amount, $entry->amount()->minor, 'a correction negates the original');
            }
            $built[] = $entry;
        }

        return $built;
    }
}
