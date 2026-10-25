<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Public availability (PUB-03, CAP-03). Read from the capacity buckets, never by counting participations. The
 * production alias is a tag-aware cache (`CachedAvailabilityReader`, tag `availability.event.<id>`) invalidated by
 * `catalog.capacity_changed` and `registration.capacity_released`.
 */
interface AvailabilityReader
{
    /**
     * @throws CapacityBucketNotFound when the event has no buckets (not published)
     */
    public function forEvent(OrganizerId $organizer, EventId $event): EventAvailability;
}
