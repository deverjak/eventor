<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * A published form version never changes (FRM-08, ADR 007): editing creates a draft of the next version.
 */
#[AsProblem(code: 'form_version_immutable', status: 409)]
final class FormVersionImmutable extends DomainException
{
    public static function create(int $number): self
    {
        return new self(\sprintf('Form version %d is published and cannot be changed.', $number));
    }
}
