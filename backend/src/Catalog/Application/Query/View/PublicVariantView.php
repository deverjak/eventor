<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

use Eventor\Shared\Domain\Money;

final readonly class PublicVariantView
{
    public function __construct(
        public string $id,
        public string $name,
        public Money $price,
        public ?int $teamSize,
        public string $capacityUnit,
        public ?int $minAge,
        public ?int $maxAge,
        public ?AvailabilityStatusView $availability,
    ) {}
}
