<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The participation state machine refused a transition: wrong source state, or a guard (capacity full on restore,
 * reservation not yet expired, order already paid).
 */
#[AsProblem(code: 'participation_transition_not_allowed', status: 409)]
final class ParticipationTransitionNotAllowed extends DomainException
{
    /**
     * @param list<string|int> $blockerCodes
     */
    public function __construct(
        public readonly string $transition,
        public readonly string $state,
        public readonly array $blockerCodes,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function context(): array
    {
        return ['transition' => $this->transition, 'state' => $this->state, 'blockers' => implode(',', array_map(strval(...), $this->blockerCodes))];
    }
}
