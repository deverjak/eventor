<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

/**
 * One attempt to create a request failed (`final = false`, the outbox retries) or it will never succeed / the retries
 * are exhausted (`final = true`): the request becomes FAILED and `payments.payment_request_failed` is published.
 */
final readonly class RecordCreationFailure
{
    public function __construct(
        public string $paymentRequestId,
        public string $error,
        public bool $final = false,
    ) {}
}
