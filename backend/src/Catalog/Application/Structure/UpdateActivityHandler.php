<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * EVT-05, PRG-03: replaces the data of an activity. Moving the start of a published activity emits
 * `catalog.activity_rescheduled` (`important` offers the notification, S12).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateActivityHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
    ) {}

    public function __invoke(UpdateActivity $command): void
    {
        $event = $this->events->get($command->eventId);
        $activity = $this->activities->get($event->id(), $command->activityId);
        $activity->assertVersion($command->expectedVersion);
        $activity->change($command->input->toDetails(), $command->input->important, $event->isLive());
    }
}
