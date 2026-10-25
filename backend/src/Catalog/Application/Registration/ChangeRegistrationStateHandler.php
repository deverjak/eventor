<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Registration;

use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * EVT-04: opens, pauses, resumes or closes the registration of an event or an activity. The state machine and its
 * guards decide (an activity opens only while the event registration is open); every change is audited and emitted as
 * `catalog.registration_state_changed`.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ChangeRegistrationStateHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private WorkflowInterface $registrationWindowStateMachine,
    ) {}

    public function __invoke(ChangeRegistrationState $command): void
    {
        $event = $this->events->get($command->eventId);
        $machine = $this->registrationWindowStateMachine;

        if (null !== $command->activityId) {
            $this->activities->get($event->id(), $command->activityId)->transitionRegistration($machine, $command->transition, $command->reason, []);

            return;
        }

        $event->transitionRegistration($machine, $command->transition, $command->reason, []);
        if (!$command->cascade) {
            return;
        }
        foreach ($this->activities->forEvent($event->id()) as $activity) {
            if ('open' === $command->transition && RegistrationState::NotOpen === $activity->registrationState()) {
                $activity->transitionRegistration($machine, 'open', $command->reason, []);
            } elseif ('close' === $command->transition && RegistrationState::Closed !== $activity->registrationState()) {
                $activity->transitionRegistration($machine, 'close', $command->reason, []);
            }
        }
    }
}
