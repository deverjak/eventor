<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Capacity;

/**
 * What one participation consumes from a bucket: a seat per person, or one seat per team (CAP-07, P1).
 */
enum CapacityUnit: string
{
    case Seat = 'seat';
    case Team = 'team';
}
