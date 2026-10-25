<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query\View;

/**
 * A bank transaction Pairrot could not allocate (reconciliation queue, PAY-06).
 */
final readonly class UnmatchedTransactionView
{
    public function __construct(
        public string $id,
        public MoneyView $amount,
        public ?string $variableSymbol,
        public string $bookedOn,
        public string $matchingResult,
        public string $handlingState,
        public ?string $createdAt,
        public ?string $resolvedAt,
        public ?string $resolution,
        public ?string $resolvedOrderId,
        public ?string $allocationReason,
    ) {}
}
