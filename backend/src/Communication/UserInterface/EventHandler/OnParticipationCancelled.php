<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Registration\Contract\Event\ParticipationCancelled;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * COM-01, "storno". A cancellation by the system is the consequence of a cancelled event, which has its own message to
 * everybody (`OnEventCancelled`); a second one per participation would only repeat it.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnParticipationCancelled
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(ParticipationCancelled $event): void
    {
        if (CancelledBy::System === $event->by) {
            return;
        }

        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::ParticipationCancelled,
            'participation_cancelled:'.$event->participationId->toString(),
            orderId: $event->orderId->toString(),
            refId: $event->participationId->toString(),
        ), TenantStamp::of($event->organizerId));
    }
}
