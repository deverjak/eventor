<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Catalog\Domain\Program\ProgramItem;
use Eventor\Catalog\Domain\Program\ProgramItemRepository;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Shared\Domain\Id\ProgramItemId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * PRG-01, PRG-02: a program item, optionally linked to an activity of the same event.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddProgramItemHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private ProgramItemRepository $items,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{programItemId: string}
     */
    public function __invoke(AddProgramItem $command): array
    {
        $event = $this->events->get($command->eventId);
        $details = $command->input->toDetails();
        if (null !== $details->activityId) {
            $this->activities->get($event->id(), $details->activityId);
        }

        $item = ProgramItem::create(ProgramItemId::generate(), $event->organizerId(), $event->id(), $details, $command->input->important, $event->isLive(), \DateTimeImmutable::createFromInterface($this->clock->now()));
        $this->items->add($item);

        return ['programItemId' => $item->id()->toString()];
    }
}
