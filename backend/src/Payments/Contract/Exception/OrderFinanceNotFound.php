<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Exception;

use Eventor\Payments\Contract\OrderFinanceInitializer;
use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\OrderId;

/**
 * The order has no finance row (unknown order, or `OrderFinanceInitializer` was not called).
 */
#[AsProblem(code: 'order_finance_not_found', status: 404)]
final class OrderFinanceNotFound extends DomainException
{
    public static function forId(OrderId $id): self
    {
        return new self(\sprintf('Order finance of order "%s" was not found.', $id->toString()));
    }
}
