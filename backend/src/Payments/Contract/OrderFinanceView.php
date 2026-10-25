<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Money;

/**
 * Financial state of one order with the split PAY-13 needs. Amounts are the folded ledger, `ledgerVersion` increases with every entry.
 */
final readonly class OrderFinanceView
{
    public function __construct(
        public OrderId $orderId,
        public FinancialStateView $summary,
        public Money $receivedTransfer,
        public Money $receivedCash,
        public Money $refunded,
        public int $ledgerVersion,
        public bool $openRefundTask,
        public bool $instructionsPending,
    ) {}
}
