<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Ledger;

/**
 * Kinds of ledger entries (chapter 05, "Druhy LedgerEntry"). The enum also says how an entry counts in the fold.
 */
enum LedgerEntryKind: string
{
    case PairrotAllocation = 'pairrot_allocation';
    case PairrotRevocation = 'pairrot_revocation';
    case Cash = 'cash';
    case CashCorrection = 'cash_correction';
    case ManualCorrection = 'manual_correction';
    case RefundSettled = 'refund_settled';

    public function source(): LedgerSource
    {
        return match ($this) {
            self::PairrotAllocation, self::PairrotRevocation => LedgerSource::Pairrot,
            self::Cash, self::CashCorrection => LedgerSource::Cash,
            self::ManualCorrection, self::RefundSettled => LedgerSource::Manual,
        };
    }

    /** Money that reached the organizer (account or hand): counts into `received`. */
    public function isReceipt(): bool
    {
        return self::RefundSettled !== $this;
    }

    public function isRefund(): bool
    {
        return self::RefundSettled === $this;
    }

    /** PAY-13 split: cash (and its corrections) versus transfer (Pairrot and manual bookings of a transfer). */
    public function isCash(): bool
    {
        return self::Cash === $this || self::CashCorrection === $this;
    }
}
