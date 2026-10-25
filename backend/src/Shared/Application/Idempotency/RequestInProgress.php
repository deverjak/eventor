<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Idempotency;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The first request with this Idempotency-Key has not finished yet; retry after a moment.
 */
#[AsProblem(code: 'request_in_progress', status: 409)]
final class RequestInProgress extends DomainException
{
    public static function create(): self
    {
        return new self('A request with this Idempotency-Key is still being processed.');
    }
}
