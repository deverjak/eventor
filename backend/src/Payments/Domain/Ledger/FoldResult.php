<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Ledger;

use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Money;

/**
 * Result of folding a ledger against the amount due (chapter 05). `received` is what the organizer holds for the
 * order: money in minus money returned (`net` of the book). `refunded` is a positive amount.
 */
final readonly class FoldResult
{
    public function __construct(
        public FinancialState $state,
        public Money $amountDue,
        public Money $received,
        public Money $receivedTransfer,
        public Money $receivedCash,
        public Money $refunded,
    ) {}

    /** What exceeds the amount due; zero when nothing does. */
    public function overpaid(): Money
    {
        $excess = $this->received->subtract($this->amountDue);

        return $excess->isPositive() ? $excess : Money::zero($this->amountDue->currency);
    }
}
