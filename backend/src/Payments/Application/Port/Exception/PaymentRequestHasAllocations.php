<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port\Exception;

/**
 * 409 `payment_request_has_allocations` when cancelling: money arrived meanwhile, the cancel becomes a supplementary request.
 */
final class PaymentRequestHasAllocations extends PaymentGatewayException
{
    public function __construct()
    {
        parent::__construct('The payment request has allocations and cannot be cancelled.');
    }

    public function errorCode(): string
    {
        return 'payment_request_has_allocations';
    }
}
