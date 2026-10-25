<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Eventor\Shared\Domain\Money;

/**
 * An allocation was revoked in Pairrot (allocation_revoked). Confirmed participations are not cancelled automatically.
 *
 * P0. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.payment_revoked', version: 1)]
#[PendingSubscribers('attention')]
final readonly class PaymentRevoked
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public PaymentRequestId $paymentRequestId,
        public Money $amount,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
