<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\RefundTaskId;
use Eventor\Shared\Domain\Money;

final readonly class RefundTaskSettled implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public RefundTaskId $refundTaskId,
        public OrderId $orderId,
        public Money $amount,
        public string $method,
        public \DateTimeImmutable $occurredAt,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'refund_task',
            entityId: $this->refundTaskId->toString(),
            action: 'refund_task.settled',
            organizerId: $this->organizerId,
            after: ['orderId' => $this->orderId->toString(), 'amountMinor' => $this->amount->minor, 'method' => $this->method],
        );
    }
}
