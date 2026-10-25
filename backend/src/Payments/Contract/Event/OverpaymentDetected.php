<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Money;

/**
 * Received money exceeds the amount due (S07). Payments opens a refund task depending on the organizer policy.
 *
 * P0. Planned subscribers: attention, payments.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.overpayment_detected', version: 1)]
final readonly class OverpaymentDetected
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public Money $overpaidAmount,
        public \DateTimeImmutable $occurredAt,
    ) {
        if (!$overpaidAmount->isPositive()) {
            throw new \InvalidArgumentException('An overpayment is a positive amount.');
        }
    }
}
