<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

/**
 * Why a payment request was issued (chapter 07, "Změna dlužné částky").
 */
enum PaymentRequestPurpose: string
{
    case Initial = 'initial';
    case Retry = 'retry';
    case Supplementary = 'supplementary';
}
