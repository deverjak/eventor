<?php

declare(strict_types=1);

namespace Eventor\Reporting\Contract;

use Eventor\Shared\Domain\Money;

/**
 * Money of an event, folded from the ledgers of its orders.
 */
final readonly class FinanceTotals
{
    public function __construct(
        public Money $amountDue,
        public Money $received,
        public Money $refunded,
        public Money $outstanding,
    ) {}
}
