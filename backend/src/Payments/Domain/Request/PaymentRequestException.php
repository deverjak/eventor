<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Request;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\PaymentRequestId;

/**
 * A payment request was asked to do something its state does not allow (retrying a request that is not failed, ...)
 * or does not exist.
 */
#[AsProblem(code: 'payment_request_state', status: 409)]
final class PaymentRequestException extends DomainException
{
    public static function notFailed(PaymentRequestId $id): self
    {
        return new self(\sprintf('Payment request "%s" has not failed; there is nothing to retry.', $id->toString()));
    }

    public static function alreadyIssued(PaymentRequestId $id): self
    {
        return new self(\sprintf('Payment request "%s" is already issued.', $id->toString()));
    }
}
