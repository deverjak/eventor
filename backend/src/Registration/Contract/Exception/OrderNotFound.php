<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\OrderId;

/**
 * No such order for this organizer.
 */
#[AsProblem(code: 'order_not_found', status: 404)]
final class OrderNotFound extends DomainException
{
    public static function forId(OrderId $id): self
    {
        return new self(\sprintf('Order "%s" was not found.', $id->toString()));
    }
}
