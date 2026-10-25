<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Exact counters of buckets for the admin view (held / confirmed / free, CAP-03) and for the capacity check of
 * Catalog. Not cached.
 */
interface CapacityQuery
{
    /**
     * @throws CapacityBucketNotFound
     */
    public function bucket(OrganizerId $organizer, CapacityBucketId $bucket): BucketOccupancy;

    /**
     * @return list<BucketOccupancy> every bucket of the event (event, activity, variant, wave level)
     */
    public function forEvent(OrganizerId $organizer, EventId $event): array;
}
