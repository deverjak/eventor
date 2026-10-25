<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Payments\Contract\Event\OverpaymentDetected;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Received money exceeds the amount due (S07).
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnOverpaymentDetected
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(OverpaymentDetected $event): void
    {
        $this->attention->openForOrder(AttentionType::Overpayment, $event->organizerId, $event->orderId, 'order', $event->orderId->toString(), [
            'amountMinor' => $event->overpaidAmount->minor,
            'currency' => $event->overpaidAmount->currency->value,
        ]);
    }
}
