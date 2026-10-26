<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Registration\Contract\Event\ParticipationTransferred;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * P1, "přepis účasti": the orderer gets the confirmation that the participation changed hands.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnParticipationTransferred
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(ParticipationTransferred $event): void
    {
        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::ParticipationTransferred,
            'participation_transferred:'.$event->newParticipationId->toString(),
            orderId: $event->orderId->toString(),
            refId: $event->newParticipationId->toString(),
        ), TenantStamp::of($event->organizerId));
    }
}
