<?php

declare(strict_types=1);

namespace Eventor\Content\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The uploaded file is not a JPEG, PNG or WebP image, or it is too large.
 */
#[AsProblem(code: 'invalid_photo', status: 422)]
final class InvalidPhoto extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
