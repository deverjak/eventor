<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * "Try again" on a failed request (ADM-03): FAILED -> PENDING, then the issuer calls Pairrot again.
 */
final readonly class RetryPaymentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $paymentRequestId,
    ) {}
}
