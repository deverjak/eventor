<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

/**
 * Eventor cancelled the request (after the call to Pairrot, or without one when it never got there).
 */
final readonly class CancelPaymentRequest
{
    public function __construct(
        public string $paymentRequestId,
        public string $reason,
    ) {}
}
