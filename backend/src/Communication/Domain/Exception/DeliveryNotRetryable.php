<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'delivery_not_retryable', status: 409)]
final class DeliveryNotRetryable extends DomainException
{
    public static function inState(string $state): self
    {
        return new self(\sprintf('Only a failed delivery can be retried (it is "%s").', $state));
    }
}
