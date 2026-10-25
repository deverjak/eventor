<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

/**
 * Step 4 of path 3: Pairrot confirmed the request (201 or 200 replayed). Stores the Pairrot id and the frozen instructions.
 */
final readonly class MarkPaymentRequestCreated
{
    public function __construct(
        public string $paymentRequestId,
        public string $pairrotId,
        public string $variableSymbol,
        public ?string $accountNumber,
        public ?string $iban,
        public string $spayd,
    ) {}
}
