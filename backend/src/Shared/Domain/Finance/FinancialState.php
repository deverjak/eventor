<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Finance;

/**
 * Derived financial state of an order (chapter 05, "Finanční stav objednávky"): a fold of the append-only ledger
 * against the current amount due, never a transition. Payments computes it; Registration, CheckIn and Reporting
 * display it, so the vocabulary lives in Shared.
 */
enum FinancialState: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overpaid = 'overpaid';
    case Disputed = 'disputed';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';
    /** Free order, or an order cancelled in full before any payment. */
    case NotRequired = 'not_required';
}
