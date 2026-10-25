<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port\Exception;

/**
 * Any other 4xx: the request itself is wrong or refused; not retryable.
 */
final class PaymentGatewayRejected extends PaymentGatewayException
{
    public function __construct(public readonly int $status, private readonly string $pairrotCode)
    {
        parent::__construct(\sprintf('Pairrot rejected the call (%d %s).', $status, $pairrotCode));
    }

    public function errorCode(): string
    {
        return $this->pairrotCode;
    }
}
