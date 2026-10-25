<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

/**
 * Why a manual refund task exists (PAY-10).
 */
enum RefundReason: string
{
    case Cancellation = 'cancellation';
    case Overpayment = 'overpayment';
    case EventCancelled = 'event_cancelled';
    case LatePayment = 'late_payment';
}
