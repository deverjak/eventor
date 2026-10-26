<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * Domain event: `Order::place()` (REG-01). Published as `registration.order_placed`; audited with neutral fields.
 */
final readonly class OrderWasPlaced implements Auditable
{
    /**
     * @param list<ParticipationId> $participationIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public string $orderNumber,
        public EventId $eventId,
        public PaymentMethod $paymentMethod,
        public Money $amountDue,
        public array $participationIds,
        public ?\DateTimeImmutable $holdExpiresAt,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            'order',
            $this->orderId->toString(),
            'order.placed',
            $this->organizerId,
            null,
            [
                'number' => $this->orderNumber,
                'eventId' => $this->eventId->toString(),
                'paymentMethod' => $this->paymentMethod->value,
                'amountDueMinor' => $this->amountDue->minor,
                'participations' => \count($this->participationIds),
            ],
        );
    }
}
