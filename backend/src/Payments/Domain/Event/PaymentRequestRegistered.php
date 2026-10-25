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
 * Domain event: the local mirror of a payment request exists (state PENDING), before Pairrot is called, so that
 * the creation can be retried safely (chapter 04, path 3).
 */
final readonly class PaymentRequestRegistered implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public PaymentRequestId $paymentRequestId,
        public int $attempt,
        public PaymentRequestPurpose $purpose,
        public Money $amount,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'payment_request',
            entityId: $this->paymentRequestId->toString(),
            action: 'payment_request.registered',
            organizerId: $this->organizerId,
            after: [
                'orderId' => $this->orderId->toString(),
                'attempt' => $this->attempt,
                'purpose' => $this->purpose->value,
                'amountMinor' => $this->amount->minor,
            ],
        );
    }
}
