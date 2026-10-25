<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Capacity;

use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * What a release gave back: the buckets that got room and the number of seats (per participation the largest of
 * its claims, summed over the released participations).
 */
final readonly class ReleasedCapacity
{
    /**
     * @param list<CapacityBucketId> $bucketIds
     */
    public function __construct(
        public array $bucketIds,
        public int $seats,
    ) {}

    public static function none(): self
    {
        return new self([], 0);
    }

    public function isEmpty(): bool
    {
        return [] === $this->bucketIds;
    }
}
