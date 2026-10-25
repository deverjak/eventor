<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Finance;

use Eventor\Shared\Domain\Id\OrderId;

interface OrderFinanceRepository
{
    public function add(OrderFinance $finance): void;

    /**
     * Plain read in the current tenant (null for an unknown order or an order of another organizer).
     */
    public function find(OrderId $order): ?OrderFinance;

    /**
     * Read with a row lock (`SELECT ... FOR UPDATE`): every writer of the ledger of one order is serialised here.
     * Only valid inside a transaction.
     */
    public function lock(OrderId $order): ?OrderFinance;
}
