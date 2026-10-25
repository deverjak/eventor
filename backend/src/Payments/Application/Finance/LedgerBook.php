<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Finance;

use Eventor\Payments\Contract\Exception\OrderFinanceNotFound;
use Eventor\Payments\Domain\Finance\OrderFinance;
use Eventor\Payments\Domain\Finance\OrderFinanceRepository;
use Eventor\Payments\Domain\Ledger\FinancialStateFold;
use Eventor\Payments\Domain\Ledger\FoldResult;
use Eventor\Payments\Domain\Ledger\LedgerEntry;
use Eventor\Payments\Domain\Ledger\LedgerEntryRepository;
use Eventor\Payments\Domain\Refund\RefundTaskRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;

/**
 * The single writer of the ledger and of its cached fold. Every path that books money (webhook, cash, corrections,
 * refunds) or changes the amount due goes through here, inside the caller's transaction, after locking the order
 * (`SELECT ... FOR UPDATE`), so two writers of one order are serialised and `ledger_version` never forks.
 */
final readonly class LedgerBook
{
    public function __construct(
        private OrderFinanceRepository $finances,
        private LedgerEntryRepository $ledger,
        private RefundTaskRepository $refunds,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws OrderFinanceNotFound unknown order, or an order of another organizer
     */
    public function lock(OrganizerId $organizer, OrderId $order): OrderFinance
    {
        $finance = $this->finances->lock($order);
        if (null === $finance || !$finance->organizerId()->equals($organizer)) {
            throw OrderFinanceNotFound::forId($order);
        }

        return $finance;
    }

    /**
     * Appends entries and rewrites the fold once.
     *
     * @param list<LedgerEntry> $entries
     * @param bool|null         $openRefund overrides the lookup when a refund task changed in this unit of work
     */
    public function append(OrderFinance $finance, array $entries, ?bool $openRefund = null): FoldResult
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $delta = Money::zero($finance->currency());
        foreach ($entries as $entry) {
            $this->ledger->add($entry);
            $delta = $delta->add($entry->amount());
            $finance->entryAppended($entry, $now);
        }

        $fold = $this->foldWith($finance, $finance->amountDue(), $entries, $openRefund);
        $finance->applyFold($fold, $delta, $now);

        return $fold;
    }

    /**
     * Registration changed the amount due. Null when nothing changed.
     */
    public function changeAmountDue(OrderFinance $finance, Money $newDue, ?string $reason): ?FoldResult
    {
        if ($finance->amountDue()->equals($newDue)) {
            return null;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $fold = $this->foldWith($finance, $newDue, []);
        $finance->changeAmountDue($newDue, $reason, $fold, $now);

        return $fold;
    }

    /**
     * An open refund task appeared or disappeared (or the dispute flag changed): the fold is rewritten.
     */
    public function refresh(OrderFinance $finance, ?bool $openRefund = null): FoldResult
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $fold = $this->foldWith($finance, $finance->amountDue(), [], $openRefund);
        $finance->applyFold($fold, Money::zero($finance->currency()), $now);

        return $fold;
    }

    /**
     * The fold as the ledger says now, without writing anything (nightly verification, admin detail).
     */
    public function foldOf(OrderFinance $finance): FoldResult
    {
        return $this->foldWith($finance, $finance->amountDue(), []);
    }

    /**
     * @param iterable<LedgerEntry> $unflushed
     */
    private function foldWith(OrderFinance $finance, Money $amountDue, iterable $unflushed, ?bool $openRefund = null): FoldResult
    {
        $entries = $this->ledger->forOrder($finance->orderId());
        foreach ($unflushed as $entry) {
            $entries[] = $entry;
        }

        return FinancialStateFold::fold(
            $entries,
            $amountDue,
            $openRefund ?? $this->refunds->hasOpenForOrder($finance->orderId()),
            $finance->isDisputed(),
        );
    }
}
