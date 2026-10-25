<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Registration\Contract\OrderAmountChangeReason;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Money;

/**
 * The amount due of an order changed (cancellation, variant change, event cancellation...). Payments recomputes the financial state and adjusts the payment request: nothing paid -> cancel and issue a new one, something paid and debt grew -> supplementary request.
 *
 * P0. Planned subscribers: payments.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.order_amount_changed', version: 1)]
final readonly class OrderAmountChanged
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public Money $previousAmountDue,
        public Money $newAmountDue,
        public OrderAmountChangeReason $reason,
        public \DateTimeImmutable $occurredAt,
    ) {
        if ($previousAmountDue->currency !== $newAmountDue->currency) {
            throw new \InvalidArgumentException('An order keeps its currency.');
        }
    }
}
