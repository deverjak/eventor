<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Payments\Contract\Event\RefundTaskOpened;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A manual refund task was opened (PAY-10).
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnRefundTaskOpened
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(RefundTaskOpened $event): void
    {
        $this->attention->openForOrder(AttentionType::RefundTask, $event->organizerId, $event->orderId, 'refund_task', $event->refundTaskId->toString(), [
            'amountMinor' => $event->amount->minor,
            'currency' => $event->amount->currency->value,
            'reason' => $event->reason->value,
        ]);
    }
}
