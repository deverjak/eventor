<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Capacity;

use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * Port: forget the cached public availability. Called by the subscribers of `catalog.capacity_changed` and
 * `registration.capacity_released`, never by the write path (which does not use the cache at all).
 */
interface AvailabilityInvalidator
{
    public function forEvent(EventId $event): void;

    /**
     * @param list<CapacityBucketId> $buckets
     */
    public function forBuckets(array $buckets): void;
}
