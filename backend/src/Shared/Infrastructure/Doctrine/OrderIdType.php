<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\OrderId;

final class OrderIdType extends AbstractIdType
{
    public const string NAME = 'order_id';

    protected function idClass(): string
    {
        return OrderId::class;
    }
}
