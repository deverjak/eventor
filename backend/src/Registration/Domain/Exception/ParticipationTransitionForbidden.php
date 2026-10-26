<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The actor lacks the permission for the transition (a guard of the participation state machine).
 */
#[AsProblem(code: 'forbidden', status: 403)]
final class ParticipationTransitionForbidden extends DomainException
{
    public static function for(string $transition): self
    {
        return new self(\sprintf('You do not have the permission for "%s".', $transition));
    }
}
