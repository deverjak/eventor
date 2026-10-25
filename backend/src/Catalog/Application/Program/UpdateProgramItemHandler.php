<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Catalog\Domain\Program\ProgramItemRepository;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * PRG-03: a program item stays editable after publication; an "important" change is announced to the participants of
 * the linked activity (or of the whole event) through `catalog.program_item_changed`.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateProgramItemHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private ProgramItemRepository $items,
        private ClockInterface $clock,
    ) {}

    public function __invoke(UpdateProgramItem $command): void
    {
        $event = $this->events->get($command->eventId);
        $item = $this->items->get($event->id(), $command->programItemId);
        $details = $command->input->toDetails();
        if (null !== $details->activityId) {
            $this->activities->get($event->id(), $details->activityId);
        }
        $item->change($details, $command->input->important, $event->isLive(), \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
