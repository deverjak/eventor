<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Workflow;

use Eventor\Registration\Domain\Exception\ParticipationTransitionForbidden;
use Eventor\Registration\Domain\Exception\ParticipationTransitionNotAllowed;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The one place where the participation entity applies a state machine transition (ADR 011). It turns the workflow's
 * exception into the module's problems: a guard that refused for lack of permission is a 403, every other refusal
 * (wrong source state, capacity, hold not expired) a 409 that names the transition and the current state.
 */
final class Transitions
{
    public const string FORBIDDEN = 'forbidden';

    /**
     * @param array<string, mixed> $context passed to the guards
     *
     * @throws ParticipationTransitionNotAllowed
     * @throws ParticipationTransitionForbidden
     */
    public static function apply(WorkflowInterface $machine, object $subject, string $transition, array $context = []): void
    {
        try {
            $machine->apply($subject, $transition, $context);
        } catch (NotEnabledTransitionException $e) {
            $messages = [];
            $codes = [];
            foreach ($e->getTransitionBlockerList() as $blocker) {
                if (self::FORBIDDEN === $blocker->getCode()) {
                    throw ParticipationTransitionForbidden::for($transition);
                }
                $messages[] = $blocker->getMessage();
                $codes[] = $blocker->getCode();
            }
            $places = array_keys($machine->getMarking($subject)->getPlaces());
            $state = (string) ($places[0] ?? 'unknown');

            throw new ParticipationTransitionNotAllowed(
                $transition,
                $state,
                $codes,
                [] === $messages
                    ? \sprintf('The transition "%s" is not possible from the state "%s".', $transition, $state)
                    : \sprintf('The transition "%s" is not possible: %s', $transition, implode(' ', $messages)),
            );
        }
    }

    /**
     * Would the transition be allowed now (state, guards)?
     *
     * @param array<string, mixed> $context
     */
    public static function can(WorkflowInterface $machine, object $subject, string $transition, array $context = []): bool
    {
        return $machine->can($subject, $transition);
    }
}
