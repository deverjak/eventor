<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;

/**
 * Domain event: the amount due of an order changed (Registration told us). Audited.
 */
final readonly class AmountDueChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public Money $previous,
        public Money $current,
        public ?string $reason,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'order_finance',
            entityId: $this->orderId->toString(),
            action: 'order_finance.amount_due_changed',
            organizerId: $this->organizerId,
            before: ['amountDueMinor' => $this->previous->minor],
            after: ['amountDueMinor' => $this->current->minor, 'reason' => $this->reason],
        );
    }
}
