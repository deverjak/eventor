<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * Why a participation needs a human (`registration.participation_requires_attention`).
 */
enum AttentionCause: string
{
    case LatePayment = 'late_payment';
    case PaymentOnCancelled = 'payment_on_cancelled';
}
