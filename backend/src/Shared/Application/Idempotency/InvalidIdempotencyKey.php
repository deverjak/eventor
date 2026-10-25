<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Idempotency;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'invalid_idempotency_key', status: 422)]
final class InvalidIdempotencyKey extends DomainException
{
    public static function malformed(): self
    {
        return new self('The Idempotency-Key header must be 16-128 characters of [A-Za-z0-9_.:-], e.g. a UUID.');
    }
}
