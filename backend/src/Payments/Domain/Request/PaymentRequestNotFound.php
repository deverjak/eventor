<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Request;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'payment_request_not_found', status: 404)]
final class PaymentRequestNotFound extends DomainException
{
    public static function forId(string $id): self
    {
        return new self(\sprintf('Payment request "%s" was not found.', $id));
    }
}
