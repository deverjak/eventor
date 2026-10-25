<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Registration\Contract\Event\ParticipationRestored;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The participation was confirmed again: its late_payment item has nothing left to do.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnParticipationRestored
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(ParticipationRestored $event): void
    {
        $this->attention->close(AttentionType::LatePayment, 'participation', $event->participationId->toString());
    }
}
