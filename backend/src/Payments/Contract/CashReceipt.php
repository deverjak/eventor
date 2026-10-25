<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Id\LedgerEntryId;

/**
 * Result of recording cash: the new ledger entry and the recomputed state. `alreadyRecorded` = an idempotent repeat.
 */
final readonly class CashReceipt
{
    public function __construct(
        public LedgerEntryId $ledgerEntryId,
        public FinancialStateView $finance,
        public bool $alreadyRecorded,
    ) {}
}
