<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;

/**
 * Domain event: finance decided to allocate an unmatched transaction to an order (PAY-06). Audited with the mandatory reason.
 */
final readonly class AllocationRequested implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public BankTransactionId $transactionId,
        public OrderId $orderId,
        public PaymentRequestId $paymentRequestId,
        public string $reason,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'unmatched_transaction',
            entityId: $this->transactionId->toString(),
            action: 'unmatched_transaction.allocation_requested',
            organizerId: $this->organizerId,
            after: ['orderId' => $this->orderId->toString(), 'paymentRequestId' => $this->paymentRequestId->toString()],
            reason: $this->reason,
        );
    }
}
