<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Payments\Contract\Event\PaymentRequestFailed;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Creating the payment request failed for good (S08).
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnPaymentRequestFailed
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(PaymentRequestFailed $event): void
    {
        $this->attention->openForOrder(AttentionType::PaymentRequestIssueFailed, $event->organizerId, $event->orderId, 'payment_request', $event->paymentRequestId->toString(), [
            'attempts' => $event->attempts,
            'lastError' => $event->lastError,
        ]);
    }
}
