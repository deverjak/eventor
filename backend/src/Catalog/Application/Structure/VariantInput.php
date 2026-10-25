<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Catalog\Domain\Structure\VariantDetails;
use Eventor\Shared\Domain\Money;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of the variant endpoints (EVT-06). `capacityLimit` only seeds a new variant (later changes go through
 * `POST /capacity`). `teamSize` above 1 makes a team variant whose capacity counts teams.
 */
final readonly class VariantInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $name,
        public Money $price,
        #[Assert\Range(min: 1, max: 100)]
        public ?int $teamSize = null,
        #[Assert\Range(min: 0, max: 120)]
        public ?int $minAge = null,
        #[Assert\Range(min: 0, max: 120)]
        public ?int $maxAge = null,
        #[Assert\PositiveOrZero]
        public ?int $capacityLimit = null,
    ) {}

    public function toDetails(): VariantDetails
    {
        return new VariantDetails(trim($this->name), $this->price, $this->teamSize, $this->minAge, $this->maxAge);
    }
}
