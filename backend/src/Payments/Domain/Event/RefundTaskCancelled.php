<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\RefundTaskId;

final readonly class RefundTaskCancelled implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public RefundTaskId $refundTaskId,
        public OrderId $orderId,
        public string $reason,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'refund_task',
            entityId: $this->refundTaskId->toString(),
            action: 'refund_task.cancelled',
            organizerId: $this->organizerId,
            after: ['orderId' => $this->orderId->toString()],
            reason: $this->reason,
        );
    }
}
