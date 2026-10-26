<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The chosen payment method is not enabled for the event, is missing while several are offered, or contradicts the
 * amount (`free` only for a zero total).
 */
#[AsProblem(code: 'payment_method_not_allowed', status: 422)]
final class PaymentMethodNotAllowed extends DomainException
{
    public static function notOffered(string $method): self
    {
        return new self(\sprintf('The payment method "%s" is not available for this event.', $method));
    }

    public static function required(): self
    {
        return new self('Choose a payment method.');
    }
}
