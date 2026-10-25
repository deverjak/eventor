<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Idempotency;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'idempotency_key_required', status: 400)]
final class IdempotencyKeyRequired extends DomainException
{
    public static function create(): self
    {
        return new self('This operation requires an Idempotency-Key header.');
    }
}
