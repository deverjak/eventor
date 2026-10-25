<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query\View;

/**
 * Financial state of one order with the PAY-13 split, its payment requests and the open refund task.
 */
final readonly class OrderFinanceDetailView
{
    /**
     * @param list<PaymentRequestView> $paymentRequests
     */
    public function __construct(
        public string $orderId,
        public string $state,
        public string $paymentMethod,
        public MoneyView $amountDue,
        public MoneyView $received,
        public MoneyView $receivedTransfer,
        public MoneyView $receivedCash,
        public MoneyView $refunded,
        public MoneyView $outstanding,
        public bool $disputed,
        public int $ledgerVersion,
        public ?string $updatedAt,
        public array $paymentRequests,
        public ?OpenRefundView $openRefundTask,
    ) {}
}
