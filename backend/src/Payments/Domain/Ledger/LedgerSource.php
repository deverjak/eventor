<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Ledger;

/**
 * Where a ledger entry came from; together with `source_ref` it is the idempotency key of the booking (S03).
 */
enum LedgerSource: string
{
    case Pairrot = 'pairrot';
    case Cash = 'cash';
    case Manual = 'manual';
}
