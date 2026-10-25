<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;

/**
 * Domain event: a `payment_request.updated` from Pairrot was applied to the mirror. `delta` is the change of
 * `paid_amount` against the last known value. Translated into `payments.payment_revoked` (negative delta) and
 * `payments.payment_dispute_opened` (cause `transaction_review_required`).
 */
final readonly class PaymentRequestMirrored
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public PaymentRequestId $paymentRequestId,
        public string $cause,
        public Money $delta,
        public string $pairrotEventId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
