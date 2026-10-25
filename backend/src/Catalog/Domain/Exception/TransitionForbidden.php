<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * A guard of a state machine refused the transition because the actor lacks the permission (guards that depend on a
 * role ask a voter, chapter 05).
 */
#[AsProblem(code: 'forbidden', status: 403)]
final class TransitionForbidden extends DomainException
{
    public static function for(string $transition): self
    {
        return new self(\sprintf('You are not allowed to perform "%s".', $transition));
    }
}
