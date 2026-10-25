<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Refund;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\RefundTaskId;

#[AsProblem(code: 'refund_task_not_open', status: 409)]
final class RefundTaskException extends DomainException
{
    public static function notOpen(RefundTaskId $id): self
    {
        return new self(\sprintf('Refund task "%s" is not open.', $id->toString()));
    }

    public static function alreadyOpen(): self
    {
        return new self('The order already has an open refund task.');
    }
}
