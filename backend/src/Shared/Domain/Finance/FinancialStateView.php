<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Finance;

use Eventor\Shared\Domain\Money;

/**
 * Money picture of an order as other modules show it (check-in detail, self-service, admin tables).
 * Produced by Payments (`FinanceQuery`), embedded in Registration DTOs.
 */
final readonly class FinancialStateView
{
    public function __construct(
        public FinancialState $state,
        public Money $amountDue,
        public Money $received,
    ) {}

    /** What the participant still has to pay; never negative. */
    public function outstanding(): Money
    {
        $outstanding = $this->amountDue->subtract($this->received);

        return $outstanding->isNegative() ? Money::zero($this->amountDue->currency) : $outstanding;
    }
}
