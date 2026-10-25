<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Ledger;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'ledger_entry_not_found', status: 404)]
final class LedgerEntryNotFound extends DomainException
{
    public static function forId(string $id): self
    {
        return new self(\sprintf('Ledger entry "%s" was not found.', $id));
    }
}
