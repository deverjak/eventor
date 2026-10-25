<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publication;

use Eventor\Catalog\Domain\Structure\EventRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Chapter 05: `completed | cancelled -> archived`. The public archive shows only marked content, never personal data.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveEventHandler
{
    public function __construct(
        private EventRepository $events,
        private WorkflowInterface $eventPublicationStateMachine,
    ) {}

    public function __invoke(ArchiveEvent $command): void
    {
        $event = $this->events->get($command->eventId);
        $event->transitionPublication($this->eventPublicationStateMachine, 'archive', [], null, []);
    }
}
