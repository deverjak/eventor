<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Unmatched;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The transaction cannot be allocated to the chosen order (already resolved, wrong order, no open request, or Pairrot
 * refused: e.g. an open review case).
 */
#[AsProblem(code: 'allocation_not_possible', status: 409)]
final class AllocationNotPossible extends DomainException
{
    public static function alreadyResolved(): self
    {
        return new self('The transaction is already resolved.');
    }

    public static function noSuchRequest(): self
    {
        return new self('The order has no payment request that can receive this transaction.');
    }

    public static function refusedByPairrot(string $code): self
    {
        return new self(\sprintf('Pairrot refused the allocation (%s).', $code));
    }
}
