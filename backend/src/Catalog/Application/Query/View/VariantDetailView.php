<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

use Eventor\Shared\Domain\Money;

final readonly class VariantDetailView
{
    public function __construct(
        public string $id,
        public string $name,
        public Money $price,
        public ?int $capacityLimit,
        public string $capacityBucketId,
        public string $capacityUnit,
        public ?int $teamSize,
        public ?int $minAge,
        public ?int $maxAge,
        public int $position,
        public int $version,
    ) {}
}
