<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query\View;

/**
 * One payment request of an order as the finance screen shows it (mirror of the request in Pairrot).
 */
final readonly class PaymentRequestView
{
    public function __construct(
        public string $id,
        public int $attempt,
        public string $purpose,
        public string $externalId,
        public string $creationState,
        public string $status,
        public MoneyView $amount,
        public MoneyView $knownPaid,
        public ?string $variableSymbol,
        public ?string $dueAt,
        public int $failedAttempts,
        public ?string $lastError,
        public ?string $issuedAt,
        public ?string $cancelledAt,
    ) {}
}
