<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port\Exception;

/**
 * Typed errors of the payment gateway (chapter 07, rule 2). The adapter turns HTTP failures into one of the
 * subclasses; the subscriber decides between retry (Unavailable) and dead letter (everything else).
 */
abstract class PaymentGatewayException extends \RuntimeException
{
    /** A stable, log-safe error code (never a response body). */
    public function errorCode(): string
    {
        return 'gateway_error';
    }
}
