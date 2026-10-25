<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * A bucket to create. `scopeId` is the id of the event, activity, variant or wave according to `scope`; the bucket id comes from Catalog.
 */
final readonly class CapacityBucketSpec
{
    public function __construct(
        public CapacityBucketId $bucketId,
        public CapacityScope $scope,
        public string $scopeId,
        public ?int $limit,
        public CapacityUnit $unit,
    ) {}
}
