<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Catalog\Domain\Structure\ActivityDetails;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of the activity endpoints (EVT-05). `capacityLimit` only seeds a new activity (later changes go through
 * `POST /capacity`, CAP-06); `important` only matters when an update moves the start of a published activity: it
 * offers the notification of the affected participants (S12).
 */
final readonly class ActivityInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $name,
        #[Assert\Length(max: 20000)]
        public ?string $description = null,
        #[Assert\Length(max: 200)]
        public ?string $location = null,
        public ?\DateTimeImmutable $startsAt = null,
        public ?\DateTimeImmutable $endsAt = null,
        public ?\DateTimeImmutable $registrationOpensAt = null,
        public ?\DateTimeImmutable $registrationClosesAt = null,
        #[Assert\Range(min: 0, max: 120)]
        public ?int $minAge = null,
        #[Assert\Range(min: 0, max: 120)]
        public ?int $maxAge = null,
        public bool $minorsOnly = false,
        #[Assert\PositiveOrZero]
        public ?int $capacityLimit = null,
        public bool $important = false,
    ) {}

    public function toDetails(): ActivityDetails
    {
        return new ActivityDetails(trim($this->name), $this->description, $this->location, $this->startsAt, $this->endsAt, $this->registrationOpensAt, $this->registrationClosesAt, $this->minAge, $this->maxAge, $this->minorsOnly);
    }
}
