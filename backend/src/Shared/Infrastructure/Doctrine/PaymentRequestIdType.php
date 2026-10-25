<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\PaymentRequestId;

final class PaymentRequestIdType extends AbstractIdType
{
    public const string NAME = 'payment_request_id';

    protected function idClass(): string
    {
        return PaymentRequestId::class;
    }
}
