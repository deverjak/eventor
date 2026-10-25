<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Payments\Contract\Event\PaymentRevoked;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * An allocation was revoked in Pairrot.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnPaymentRevoked
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(PaymentRevoked $event): void
    {
        $this->attention->openForOrder(AttentionType::PaymentRevoked, $event->organizerId, $event->orderId, 'payment_request', $event->paymentRequestId->toString(), [
            'amountMinor' => $event->amount->minor,
            'currency' => $event->amount->currency->value,
        ]);
    }
}
