<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\CheckInStationId;

final class CheckInStationIdType extends AbstractIdType
{
    public const string NAME = 'check_in_station_id';

    protected function idClass(): string
    {
        return CheckInStationId::class;
    }
}
