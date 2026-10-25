<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\EventPublisher;

use Eventor\Catalog\Contract\Event\ProgramItemChanged;
use Eventor\Catalog\Domain\Event\ProgramItemModified;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A program change of a published event -> `catalog.program_item_changed` (PRG-03); `important` offers the
 * notification of the participants.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishProgramItemChanged
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ProgramItemModified $event): void
    {
        if (!$event->live) {
            return;
        }
        $this->eventBus->dispatch(
            new ProgramItemChanged($event->organizerId, $event->eventId, $event->programItemId, $event->activityId, $event->important, \DateTimeImmutable::createFromInterface($this->clock->now())),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
