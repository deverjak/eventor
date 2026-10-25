<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * Result of a limit change: the old and new limit and whether the new limit is below the occupancy (warning `capacity_below_occupancy`).
 */
final readonly class CapacityLimitChange
{
    public function __construct(
        public CapacityBucketId $bucketId,
        public ?int $previousLimit,
        public ?int $newLimit,
        public int $held,
        public int $confirmed,
        public bool $belowOccupancy,
    ) {}
}
