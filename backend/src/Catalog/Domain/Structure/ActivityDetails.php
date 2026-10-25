<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Eventor\Catalog\Domain\Exception\InvalidCatalogData;

/**
 * What the organizer edits about an activity (EVT-05). Replaced as a whole.
 */
final readonly class ActivityDetails
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $location,
        public ?\DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public ?\DateTimeImmutable $registrationOpensAt,
        public ?\DateTimeImmutable $registrationClosesAt,
        public ?int $minAge,
        public ?int $maxAge,
        public bool $minorsOnly,
    ) {
        if (null !== $startsAt && null !== $endsAt && $endsAt < $startsAt) {
            throw InvalidCatalogData::because('An activity cannot end before it starts.');
        }
        if (null !== $registrationOpensAt && null !== $registrationClosesAt && $registrationClosesAt <= $registrationOpensAt) {
            throw InvalidCatalogData::because('Registration of an activity must close after it opens.');
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
            'name' => [$this->name, $other->name],
            'description' => [$this->description, $other->description],
            'location' => [$this->location, $other->location],
            'min_age' => [$this->minAge, $other->minAge],
            'max_age' => [$this->maxAge, $other->maxAge],
            'minors_only' => [$this->minorsOnly, $other->minorsOnly],
            'starts_at' => [$this->startsAt?->getTimestamp(), $other->startsAt?->getTimestamp()],
            'ends_at' => [$this->endsAt?->getTimestamp(), $other->endsAt?->getTimestamp()],
            'registration_opens_at' => [$this->registrationOpensAt?->getTimestamp(), $other->registrationOpensAt?->getTimestamp()],
            'registration_closes_at' => [$this->registrationClosesAt?->getTimestamp(), $other->registrationClosesAt?->getTimestamp()],
        ] as $field => [$mine, $theirs]) {
            if ($mine !== $theirs) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
