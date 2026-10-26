<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Registration\Contract\Event\ParticipationRestored;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A participation came back after a late payment (S05): the participant is told it is confirmed again.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnParticipationRestored
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(ParticipationRestored $event): void
    {
        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::ParticipationConfirmed,
            'participation_restored:'.$event->participationId->toString(),
            orderId: $event->orderId->toString(),
            refId: $event->participationId->toString(),
        ), TenantStamp::of($event->organizerId));
    }
}
