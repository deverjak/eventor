<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Registration\Contract\OrderAmountChangeReason;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;

/**
 * Domain event: the amount due of an order changed (cancellation, event cancelled). Published as
 * `registration.order_amount_changed`, which Payments turns into a recomputed financial state and, if needed, a refund task.
 */
final readonly class OrderAmountDueChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public Money $previous,
        public Money $new,
        public OrderAmountChangeReason $reason,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            'order',
            $this->orderId->toString(),
            'order.amount_due_changed',
            $this->organizerId,
            ['amountDueMinor' => $this->previous->minor],
            ['amountDueMinor' => $this->new->minor],
            $this->reason->value,
        );
    }
}
