<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

/**
 * The outbox gave up on an order event (retries exhausted, S08): every request of the order that is still PENDING
 * becomes FAILED and `payments.payment_request_failed` opens the attention item with the action "try again".
 */
final readonly class FailPendingPaymentRequests
{
    public function __construct(
        public string $orderId,
        public string $error,
    ) {}
}
