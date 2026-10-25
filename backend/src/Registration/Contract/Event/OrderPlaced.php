<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * An order was placed (REG-01). Payments creates the payment request for `bank_transfer` with a positive amount.
 *
 * P0. Planned subscribers: payments, communication, reporting.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.order_placed', version: 1)]
#[PendingSubscribers('payments', 'communication', 'reporting')]
final readonly class OrderPlaced
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
        public \DateTimeImmutable $occurredAt,
    ) {
        if ([] === $participationIds) {
            throw new \InvalidArgumentException('An order has at least one participation.');
        }
        if ($amountDue->isNegative()) {
            throw new \InvalidArgumentException('The amount due cannot be negative.');
        }
    }
}
