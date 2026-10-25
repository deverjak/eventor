<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Refund;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'refund_task_not_found', status: 404)]
final class RefundTaskNotFound extends DomainException
{
    public static function forId(string $id): self
    {
        return new self(\sprintf('Refund task "%s" was not found.', $id));
    }
}
