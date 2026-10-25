<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * Pairrot did not answer (network, 5xx, 429, open circuit breaker). Retryable; crosses the boundary for the onboarding wizard.
 */
#[AsProblem(code: 'pairrot_unavailable', status: 503)]
final class PairrotUnavailable extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
