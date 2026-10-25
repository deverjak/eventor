<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Doctrine;

use Eventor\Identity\Domain\DeviceToken\DeviceTokenId;
use Eventor\Shared\Infrastructure\Doctrine\AbstractIdType;

final class DeviceTokenIdType extends AbstractIdType
{
    public const string NAME = 'device_token_id';

    protected function idClass(): string
    {
        return DeviceTokenId::class;
    }
}
