<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Ledger;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\LedgerEntryId;

/**
 * Only an uncorrected cash entry can be compensated as cash (S11); everything else is booked as a manual correction.
 */
#[AsProblem(code: 'ledger_entry_not_correctable', status: 409)]
final class NotCorrectable extends DomainException
{
    public static function create(LedgerEntryId $id): self
    {
        return new self(\sprintf('Ledger entry "%s" is not a cash entry, or it was already corrected.', $id->toString()));
    }
}
