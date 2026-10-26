<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The text of a template or campaign does not compile in the sandbox (unknown variable, forbidden tag or filter).
 */
#[AsProblem(code: 'template_invalid', status: 422)]
final class InvalidTemplate extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
