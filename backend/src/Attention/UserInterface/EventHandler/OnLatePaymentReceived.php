<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Registration\Contract\Event\LatePaymentReceived;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Money for expired participations (S05): one late_payment item per participation, unless Registration restores them itself (S06).
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnLatePaymentReceived
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(LatePaymentReceived $event): void
    {
        if ($event->autoRestore && $event->capacityAvailable) {
            return;
        }
        foreach ($event->participationIds as $participation) {
            $this->attention->openForOrder(AttentionType::LatePayment, $event->organizerId, $event->orderId, 'participation', $participation->toString(), ['cause' => 'late_payment']);
        }
    }
}
