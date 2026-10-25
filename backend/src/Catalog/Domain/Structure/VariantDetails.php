<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Eventor\Catalog\Domain\Exception\InvalidCatalogData;
use Eventor\Shared\Domain\Money;

/**
 * What the organizer edits about a variant (EVT-06): the price level of an activity. `teamSize` > 1 makes it a team
 * or pair variant whose capacity counts teams, not seats.
 */
final readonly class VariantDetails
{
    public function __construct(
        public string $name,
        public Money $price,
        public ?int $teamSize,
        public ?int $minAge,
        public ?int $maxAge,
    ) {
        if ($price->isNegative()) {
            throw InvalidCatalogData::because('A price cannot be negative.');
        }
        if (null !== $teamSize && $teamSize < 1) {
            throw InvalidCatalogData::because('A team has at least one person.');
        }
        if (null !== $minAge && null !== $maxAge && $minAge > $maxAge) {
            throw InvalidCatalogData::because('The minimum age cannot be higher than the maximum age.');
        }
    }

    /**
     * @return list<string>
     */
    public function diff(self $other): array
    {
        $changed = [];
        foreach ([
            'name' => $this->name !== $other->name,
            'price' => !$this->price->equals($other->price),
            'team_size' => $this->teamSize !== $other->teamSize,
            'min_age' => $this->minAge !== $other->minAge,
            'max_age' => $this->maxAge !== $other->maxAge,
        ] as $field => $differs) {
            if ($differs) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
