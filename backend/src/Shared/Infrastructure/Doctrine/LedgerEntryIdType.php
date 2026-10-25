<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\LedgerEntryId;

final class LedgerEntryIdType extends AbstractIdType
{
    public const string NAME = 'ledger_entry_id';

    protected function idClass(): string
    {
        return LedgerEntryId::class;
    }
}
