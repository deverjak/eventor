<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Unmatched;

use Eventor\Shared\Domain\Id\BankTransactionId;

interface UnmatchedTransactionRepository
{
    public function add(UnmatchedTransaction $transaction): void;

    public function find(BankTransactionId $id): ?UnmatchedTransaction;

    /**
     * By Pairrot's transaction id, with a row lock (inside a transaction).
     */
    public function lockByPairrotId(string $pairrotTxId): ?UnmatchedTransaction;
}
