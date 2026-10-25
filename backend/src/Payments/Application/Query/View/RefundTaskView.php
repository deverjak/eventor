<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query\View;

/**
 * A refund task in the manual refund queue (PAY-10, PAY-11).
 */
final readonly class RefundTaskView
{
    public function __construct(
        public string $id,
        public string $orderId,
        public MoneyView $amount,
        public string $reason,
        public string $status,
        public ?string $note,
        public ?string $createdAt,
        public ?string $settledAt,
        public ?string $settleMethod,
        public ?string $settleReference,
        public ?string $cancelledAt,
    ) {}
}
