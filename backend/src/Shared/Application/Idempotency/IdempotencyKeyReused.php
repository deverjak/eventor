<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Idempotency;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The same Idempotency-Key was sent with a different request body.
 */
#[AsProblem(code: 'idempotency_key_reused', status: 409)]
final class IdempotencyKeyReused extends DomainException
{
    public static function create(): self
    {
        return new self('This Idempotency-Key was already used with a different request.');
    }
}
