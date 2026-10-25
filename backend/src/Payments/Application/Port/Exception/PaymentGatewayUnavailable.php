<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port\Exception;

/**
 * Network failure, timeout, 5xx, 408, 429 or an open circuit breaker: retryable (outbox backoff, S08).
 */
final class PaymentGatewayUnavailable extends PaymentGatewayException
{
    public function __construct(string $message, private readonly string $code_ = 'pairrot_unavailable', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return $this->code_;
    }
}
