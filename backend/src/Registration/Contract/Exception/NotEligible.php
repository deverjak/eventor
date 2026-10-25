<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The participant does not meet the conditions of the variant: age at the reference date, category, team size (chapter 04, path 2, step 2).
 */
#[AsProblem(code: 'not_eligible', status: 422)]
final class NotEligible extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
