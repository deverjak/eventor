<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The decision is not allowed for this requirement (declining a mandatory one, withdrawing a non-revocable one).
 */
#[AsProblem(code: 'confirmation_not_allowed', status: 422)]
final class ConfirmationNotAllowed extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
