<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

/**
 * Mirror of the payment request in Pairrot (chapter 05, "Platební požadavek"). It never decides the financial state of the order; the ledger does.
 */
enum PaymentRequestStatus: string
{
    case Requested = 'requested';
    case Issued = 'issued';
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overpaid = 'overpaid';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
