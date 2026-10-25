<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Workflow;

use Eventor\Catalog\Domain\Exception\TransitionForbidden;
use Eventor\Catalog\Domain\Exception\TransitionNotAllowed;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The one place where entities apply a state machine transition (ADR 011: `Participation::confirm()` style). It turns
 * the workflow's exception into the module's problems: a guard that refused for lack of permission is a 403, every
 * other refusal (wrong source state, domain guard) a 409 that names workflow, transition and current state.
 */
final class Transitions
{
    public const string FORBIDDEN = 'forbidden';

    /**
     * @param array<string, mixed> $context passed to the guards and to the marking store (`actor`, `reason`)
     *
     * @throws TransitionNotAllowed
     * @throws TransitionForbidden
     */
    public static function apply(WorkflowInterface $machine, object $subject, string $transition, array $context = []): void
    {
        try {
            $machine->apply($subject, $transition, $context);
        } catch (NotEnabledTransitionException $e) {
            $messages = [];
            foreach ($e->getTransitionBlockerList() as $blocker) {
                if (self::FORBIDDEN === $blocker->getCode()) {
                    throw TransitionForbidden::for($transition);
                }
                $messages[] = $blocker->getMessage();
            }
            $places = array_keys($machine->getMarking($subject)->getPlaces());
            $state = (string) ($places[0] ?? 'unknown');

            throw new TransitionNotAllowed(
                $machine->getName(),
                $transition,
                $state,
                [] === $messages
                    ? \sprintf('The transition "%s" is not possible from the state "%s".', $transition, $state)
                    : \sprintf('The transition "%s" is not possible: %s', $transition, implode(' ', $messages)),
            );
        }
    }
}
