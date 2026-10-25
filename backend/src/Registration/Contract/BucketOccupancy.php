<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * The three counts of a bucket (CAP-03). `limit` null = unlimited; `overbooked` after a limit was lowered below occupancy (CAP-06).
 */
final readonly class BucketOccupancy
{
    public function __construct(
        public CapacityBucketId $bucketId,
        public CapacityScope $scope,
        public string $scopeId,
        public ?int $limit,
        public int $held,
        public int $confirmed,
        public bool $overbooked,
    ) {}

    public function available(): ?int
    {
        return null === $this->limit ? null : max(0, $this->limit - $this->held - $this->confirmed);
    }
}
