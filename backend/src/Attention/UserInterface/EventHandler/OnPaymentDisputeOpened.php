<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Payments\Contract\Event\PaymentDisputeOpened;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Pairrot opened a review case for the payment; stored as pairrot_review.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnPaymentDisputeOpened
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(PaymentDisputeOpened $event): void
    {
        $this->attention->openForOrder(AttentionType::PairrotReview, $event->organizerId, $event->orderId, 'review_case', $event->reviewCaseId, [
            'paymentRequestId' => $event->paymentRequestId->toString(),
        ]);
    }
}
