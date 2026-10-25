<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;

/**
 * Domain event: creating the request in Pairrot failed for good (retries exhausted, or a permanent rejection).
 * Translated into `payments.payment_request_failed`.
 */
final readonly class PaymentRequestCreationFailed implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public PaymentRequestId $paymentRequestId,
        public int $attempts,
        public string $lastError,
        public \DateTimeImmutable $occurredAt,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'payment_request',
            entityId: $this->paymentRequestId->toString(),
            action: 'payment_request.creation_failed',
            organizerId: $this->organizerId,
            after: ['attempts' => $this->attempts, 'lastError' => $this->lastError],
        );
    }
}
