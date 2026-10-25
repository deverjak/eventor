<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;

/**
 * One variant (price level) of an activity. A participation occupies one `unit` in the variant bucket plus the activity and event buckets.
 * `teamSize` is set for team variants (CAP-07, P1).
 */
final readonly class VariantSnapshot
{
    public function __construct(
        public VariantId $id,
        public ActivityId $activityId,
        public string $name,
        public Money $price,
        public ?CapacityBucketId $capacityBucketId,
        public CapacityUnit $capacityUnit,
        public ?int $teamSize,
        public ?int $minAge,
        public ?int $maxAge,
    ) {}
}
