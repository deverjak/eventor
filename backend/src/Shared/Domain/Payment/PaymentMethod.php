<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Payment;

/**
 * How an order is paid (PAY-01). Shared because the catalogue offers it, Registration stores it on the order,
 * Payments reacts to it and integration events carry it. `Free` is chosen by the system for a zero amount.
 */
enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Free = 'free';
}
