<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Capacity;

use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * The seats one order needs from one bucket, summed over all its participations and split by target counter.
 */
final readonly class BucketDemand
{
    public function __construct(
        public CapacityBucketId $bucketId,
        public int $held,
        public int $confirmed,
    ) {}

    public function total(): int
    {
        return $this->held + $this->confirmed;
    }
}
