<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\EventPublisher;

use Eventor\CheckIn\Contract\Event\CheckInVoided;
use Eventor\CheckIn\Contract\Event\EscalationRaised;
use Eventor\CheckIn\Contract\Event\ParticipationCheckedIn;
use Eventor\CheckIn\Domain\Event\CheckedIn;
use Eventor\CheckIn\Domain\Event\CheckInWasVoided;
use Eventor\CheckIn\Domain\Event\EscalationWasRaised;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates the internal domain events of CheckIn into the public integration events, synchronously inside the
 * command transaction, so the outbox row commits (or rolls back) with the check-in (ADR 003). Ids, times and
 * situations only; never a name.
 */
final readonly class PublishCheckInEvents
{
    public function __construct(
        #[Target('event.bus')]
        private MessageBusInterface $eventBus,
    ) {}

    #[AsMessageHandler(bus: 'event.bus')]
    public function checkedIn(CheckedIn $event): void
    {
        $this->publish($event->organizerId, new ParticipationCheckedIn($event->organizerId, $event->checkInId, $event->participationId, $event->listId, $event->stationId, $event->occurredAt, $event->occurredAt));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function voided(CheckInWasVoided $event): void
    {
        $this->publish($event->organizerId, new CheckInVoided($event->organizerId, $event->checkInId, $event->participationId, $event->replacedBy, $event->occurredAt));
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function escalated(EscalationWasRaised $event): void
    {
        $this->publish($event->organizerId, new EscalationRaised($event->organizerId, $event->participationId, $event->listId, $event->situation, $event->occurredAt));
    }

    private function publish(OrganizerId $organizer, object $event): void
    {
        $this->eventBus->dispatch($event, [TenantStamp::of($organizer)]);
    }
}
