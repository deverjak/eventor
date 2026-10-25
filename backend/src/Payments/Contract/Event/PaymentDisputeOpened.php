<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;

/**
 * Pairrot opened a review case for the payment (transaction_review_required). `reviewCaseId` is Pairrot's identifier.
 *
 * P0. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.payment_dispute_opened', version: 1)]
final readonly class PaymentDisputeOpened
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public PaymentRequestId $paymentRequestId,
        public string $reviewCaseId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
