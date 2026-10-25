<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * Availability of one bucket for display; `available` null = unlimited.
 */
final readonly class BucketAvailability
{
    public function __construct(
        public CapacityBucketId $bucketId,
        public CapacityScope $scope,
        public string $scopeId,
        public ?int $available,
    ) {}
}
