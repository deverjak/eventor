<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Ledger;

use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Money;

/**
 * `FinancialState::fold(entries, amountDue, openRefund, disputed)` of chapter 05: a pure function, no clock, no
 * database. The financial state is never a transition; it is whatever the ledger says against today's amount due.
 *
 *     received = sum(pairrot_allocation, pairrot_revocation, cash, cash_correction, manual_correction)
 *     refunded = sum(refund_settled)                 (negative in the ledger)
 *     net      = received + refunded
 *
 *     disputed                                     -> disputed
 *     amount_due == 0 and net == 0                 -> refunded (when something was refunded), else not_required
 *     amount_due == 0 and net < 0                  -> refunded
 *     net <= 0                                     -> unpaid
 *     0 < net < amount_due                         -> partially_paid
 *     net == amount_due                            -> paid
 *     net > amount_due and an open refund task     -> refund_pending
 *     net > amount_due                             -> overpaid
 */
final class FinancialStateFold
{
    /**
     * @param iterable<LedgerEntry> $entries
     */
    public static function fold(iterable $entries, Money $amountDue, bool $openRefund = false, bool $disputed = false): FoldResult
    {
        $currency = $amountDue->currency;
        $transfer = Money::zero($currency);
        $cash = Money::zero($currency);
        $refundedSigned = Money::zero($currency);

        foreach ($entries as $entry) {
            $kind = $entry->kind();
            if ($kind->isRefund()) {
                $refundedSigned = $refundedSigned->add($entry->amount());
            } elseif ($kind->isCash()) {
                $cash = $cash->add($entry->amount());
            } else {
                $transfer = $transfer->add($entry->amount());
            }
        }

        $net = $transfer->add($cash)->add($refundedSigned);
        $refunded = $refundedSigned->negate();

        return new FoldResult(
            self::stateOf($net, $amountDue, $refunded, $openRefund, $disputed),
            $amountDue,
            $net,
            $transfer,
            $cash,
            $refunded,
        );
    }

    private static function stateOf(Money $net, Money $amountDue, Money $refunded, bool $openRefund, bool $disputed): FinancialState
    {
        if ($disputed) {
            return FinancialState::Disputed;
        }

        if ($amountDue->isZero()) {
            if ($net->isPositive()) {
                return $openRefund ? FinancialState::RefundPending : FinancialState::Overpaid;
            }

            return $net->isNegative() || !$refunded->isZero() ? FinancialState::Refunded : FinancialState::NotRequired;
        }

        if (!$net->isPositive()) {
            return FinancialState::Unpaid;
        }
        if ($net->lessThan($amountDue)) {
            return FinancialState::PartiallyPaid;
        }
        if ($net->equals($amountDue)) {
            return FinancialState::Paid;
        }

        return $openRefund ? FinancialState::RefundPending : FinancialState::Overpaid;
    }
}
