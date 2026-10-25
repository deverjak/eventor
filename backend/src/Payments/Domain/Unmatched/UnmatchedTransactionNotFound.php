<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Unmatched;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'unmatched_transaction_not_found', status: 404)]
final class UnmatchedTransactionNotFound extends DomainException
{
    public static function forId(string $id): self
    {
        return new self(\sprintf('Unmatched transaction "%s" was not found.', $id));
    }
}
