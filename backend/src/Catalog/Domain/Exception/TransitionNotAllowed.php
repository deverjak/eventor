<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * A state machine refuses the transition from the current state (or a guard blocked it).
 */
#[AsProblem(code: 'transition_not_allowed', status: 409)]
final class TransitionNotAllowed extends DomainException
{
    public function __construct(
        private readonly string $workflow,
        private readonly string $transition,
        private readonly string $state,
        string $detail,
    ) {
        parent::__construct($detail);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function context(): array
    {
        return ['workflow' => $this->workflow, 'transition' => $this->transition, 'state' => $this->state];
    }
}
