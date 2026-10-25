<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;

/**
 * Domain event: the request was cancelled by Eventor (amount changed, order cancelled), in Pairrot or before it ever
 * got there. Not published: the participant gets new instructions through the new request.
 */
final readonly class PaymentRequestCancelled implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public PaymentRequestId $paymentRequestId,
        public string $reason,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'payment_request',
            entityId: $this->paymentRequestId->toString(),
            action: 'payment_request.cancelled',
            organizerId: $this->organizerId,
            after: ['orderId' => $this->orderId->toString()],
            reason: $this->reason,
        );
    }
}
