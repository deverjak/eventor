<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Writing facade for capacity configuration. Catalog owns the configured limits (activity / variant settings) and
 * decides the bucket ids; Registration owns the `capacity_bucket` table. It runs in the transaction of the caller
 * (`PublishEvent`, `ChangeCapacity`), so only a handler already on `command.bus` may call it.
 *
 * Deviation from the book, which has Catalog create the buckets directly (chapter 04, path 1) while chapter 02/03
 * give the table to Registration: the table stays Registration's, the call is explicit.
 */
interface CapacityProvisioner
{
    /**
     * Creates the buckets of a publishing event with held = confirmed = 0. Idempotent per bucket id.
     *
     * @param list<CapacityBucketSpec> $buckets
     */
    public function provision(OrganizerId $organizer, EventId $event, array $buckets): void;

    /**
     * Sets the limit without checking occupancy (CAP-06, ADR 002); null = unlimited. Never cancels anything.
     * Sets or clears `overbooked_allowed`. It releases nothing by itself: the caller publishes `catalog.capacity_changed`
     * and Registration's subscriber answers a raised limit with `registration.capacity_released` (cause `limit_raised`).
     *
     * @throws CapacityBucketNotFound
     */
    public function changeLimit(OrganizerId $organizer, CapacityBucketId $bucket, ?int $newLimit, string $reason): CapacityLimitChange;
}
