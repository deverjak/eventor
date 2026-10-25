<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Payments\Contract\PaymentRequestPurpose;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;

/**
 * Domain event: Pairrot confirmed the request (201 or 200 replayed). Translated into `payments.payment_request_issued`.
 */
final readonly class PaymentRequestCreated implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public PaymentRequestId $paymentRequestId,
        public string $variableSymbol,
        public Money $amount,
        public \DateTimeImmutable $dueAt,
        public PaymentRequestPurpose $purpose,
        public \DateTimeImmutable $occurredAt,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'payment_request',
            entityId: $this->paymentRequestId->toString(),
            action: 'payment_request.issued',
            organizerId: $this->organizerId,
            after: ['orderId' => $this->orderId->toString(), 'variableSymbol' => $this->variableSymbol],
        );
    }
}
