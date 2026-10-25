<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publication;

use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Catalog\Domain\Structure\Activity;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Chapter 05: `published -> completed`, possible after the event has ended (guard). Closes the registration windows.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CompleteEventHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private WorkflowInterface $eventPublicationStateMachine,
        private WorkflowInterface $registrationWindowStateMachine,
    ) {}

    public function __invoke(CompleteEvent $command): void
    {
        $event = $this->events->get($command->eventId);
        $activities = $this->activities->forEvent($event->id());

        $event->transitionPublication(
            $this->eventPublicationStateMachine,
            'complete',
            array_map(static fn(Activity $activity) => $activity->id(), $activities),
            null,
            [],
        );

        if (RegistrationState::Closed !== $event->registrationState()) {
            $event->transitionRegistration($this->registrationWindowStateMachine, 'close', 'The event is over.', []);
        }
        foreach ($activities as $activity) {
            if (RegistrationState::Closed !== $activity->registrationState()) {
                $activity->transitionRegistration($this->registrationWindowStateMachine, 'close', 'The event is over.', []);
            }
        }
    }
}
