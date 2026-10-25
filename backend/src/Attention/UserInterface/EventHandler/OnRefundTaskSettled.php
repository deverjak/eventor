<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Payments\Contract\Event\RefundTaskSettled;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Finance settled the refund task: its item closes.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnRefundTaskSettled
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(RefundTaskSettled $event): void
    {
        $this->attention->close(AttentionType::RefundTask, 'refund_task', $event->refundTaskId->toString());
    }
}
