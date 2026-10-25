<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;

/**
 * Domain event: finance pressed "try again" on a failed request (ADM-03); the state is PENDING again.
 */
final readonly class PaymentRequestRetried implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public PaymentRequestId $paymentRequestId,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'payment_request',
            entityId: $this->paymentRequestId->toString(),
            action: 'payment_request.retried',
            organizerId: $this->organizerId,
        );
    }
}
