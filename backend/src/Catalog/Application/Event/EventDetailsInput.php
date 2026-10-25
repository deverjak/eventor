<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Event;

use Eventor\Catalog\Domain\Structure\EventDetails;
use Eventor\Catalog\Domain\Structure\EventVisibility;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The descriptive data of an event as the admin API receives it (EVT-01, EVT-04, EVT-11); used by create and update.
 */
final readonly class EventDetailsInput
{
    /**
     * @param list<VenueInput> $venues
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $name,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        #[Assert\Length(max: 500)]
        public ?string $shortDescription = null,
        #[Assert\Length(max: 20000)]
        public ?string $description = null,
        #[Assert\Url(requireTld: false)]
        #[Assert\Length(max: 500)]
        public ?string $coverImageUrl = null,
        #[Assert\Length(max: 200)]
        public ?string $contactName = null,
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public ?string $contactEmail = null,
        #[Assert\Length(max: 50)]
        public ?string $contactPhone = null,
        #[Assert\Length(max: 64)]
        public string $timezone = 'Europe/Prague',
        public ?\DateTimeImmutable $ageReferenceDate = null,
        #[Assert\Choice(choices: ['public', 'unlisted'])]
        public string $visibility = 'public',
        #[Assert\Valid]
        #[Assert\Count(max: 10)]
        public array $venues = [],
        public ?\DateTimeImmutable $registrationOpensAt = null,
        public ?\DateTimeImmutable $registrationClosesAt = null,
    ) {}

    public function toDetails(): EventDetails
    {
        return new EventDetails(
            trim($this->name),
            $this->shortDescription,
            $this->description,
            $this->coverImageUrl,
            $this->contactName,
            $this->contactEmail,
            $this->contactPhone,
            $this->timezone,
            $this->startsAt,
            $this->endsAt,
            $this->ageReferenceDate,
            EventVisibility::tryFrom($this->visibility) ?? EventVisibility::Public,
            array_map(static fn(VenueInput $venue) => $venue->toVenue(), $this->venues),
            $this->registrationOpensAt,
            $this->registrationClosesAt,
        );
    }
}
