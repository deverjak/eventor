<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\DeliveryId;

final class DeliveryIdType extends AbstractIdType
{
    public const string NAME = 'delivery_id';

    protected function idClass(): string
    {
        return DeliveryId::class;
    }
}
