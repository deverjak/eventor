<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Catalog\Domain\Program\ProgramItemRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Removes a program item (it has no registrations or money attached). The removal is recorded by the event, because
 * the removed row leaves the unit of work.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveProgramItemHandler
{
    public function __construct(
        private EventRepository $events,
        private ProgramItemRepository $items,
    ) {}

    public function __invoke(RemoveProgramItem $command): void
    {
        $event = $this->events->get($command->eventId);
        $item = $this->items->get($event->id(), $command->programItemId);
        $event->noteProgramItemRemoved($item->id(), $item->activityId(), $command->important);
        $this->items->remove($item);
    }
}
