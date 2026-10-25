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
 * Chapter 05: `published -> cancelled`. The registration of the event and of all its activities is closed in the same
 * transaction; `catalog.event_cancelled` starts the rest of the cancellation (refund tasks, messages, check-in).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CancelEventHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private WorkflowInterface $eventPublicationStateMachine,
        private WorkflowInterface $registrationWindowStateMachine,
    ) {}

    public function __invoke(CancelEvent $command): void
    {
        $event = $this->events->get($command->eventId);
        $activities = $this->activities->forEvent($event->id());

        $event->transitionPublication(
            $this->eventPublicationStateMachine,
            'cancel',
            array_map(static fn(Activity $activity) => $activity->id(), $activities),
            $command->reason,
            [],
        );

        $reason = 'The event was cancelled.';
        if (RegistrationState::Closed !== $event->registrationState()) {
            $event->transitionRegistration($this->registrationWindowStateMachine, 'close', $reason, []);
        }
        foreach ($activities as $activity) {
            if (RegistrationState::Closed !== $activity->registrationState()) {
                $activity->transitionRegistration($this->registrationWindowStateMachine, 'close', $reason, []);
            }
        }
    }
}
