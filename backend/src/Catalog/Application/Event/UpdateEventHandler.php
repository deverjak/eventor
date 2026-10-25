<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Event;

use Eventor\Catalog\Domain\Structure\EventRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * EVT-01/04/11: replaces the descriptive data. A live event announces the changed fields (`catalog.event_details_changed`).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateEventHandler
{
    public function __construct(
        private EventRepository $events,
        private ClockInterface $clock,
    ) {}

    public function __invoke(UpdateEvent $command): void
    {
        $event = $this->events->get($command->eventId);
        $event->assertVersion($command->expectedVersion);
        $event->changeDetails($command->details->toDetails(), \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
