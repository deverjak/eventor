<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Eventor\Catalog\Domain\Exception\InvalidCatalogData;

/**
 * The descriptive data of an event that the organizer edits as a whole (EVT-01, EVT-04, EVT-11). Replaces the previous
 * details; `diff()` names the fields that differ so the change can be audited and announced.
 */
final readonly class EventDetails
{
    /**
     * @param list<Venue> $venues
     */
    public function __construct(
        public string $name,
        public ?string $shortDescription,
        public ?string $description,
        public ?string $coverImageUrl,
        public ?string $contactName,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public string $timezone,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public ?\DateTimeImmutable $ageReferenceDate,
        public EventVisibility $visibility,
        public array $venues,
        public ?\DateTimeImmutable $registrationOpensAt,
        public ?\DateTimeImmutable $registrationClosesAt,
    ) {
        if ($endsAt < $startsAt) {
            throw InvalidCatalogData::because('The event cannot end before it starts.');
        }
        if (null !== $registrationOpensAt && null !== $registrationClosesAt && $registrationClosesAt <= $registrationOpensAt) {
            throw InvalidCatalogData::because('Registration must close after it opens.');
        }
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception) {
            throw InvalidCatalogData::because(\sprintf('"%s" is not a time zone.', $timezone));
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
            'short_description' => [$this->shortDescription, $other->shortDescription],
            'description' => [$this->description, $other->description],
            'cover_image_url' => [$this->coverImageUrl, $other->coverImageUrl],
            'contact_name' => [$this->contactName, $other->contactName],
            'contact_email' => [$this->contactEmail, $other->contactEmail],
            'contact_phone' => [$this->contactPhone, $other->contactPhone],
            'timezone' => [$this->timezone, $other->timezone],
            'visibility' => [$this->visibility, $other->visibility],
        ] as $field => [$mine, $theirs]) {
            if ($mine !== $theirs) {
                $changed[] = $field;
            }
        }
        foreach ([
            'starts_at' => [$this->startsAt, $other->startsAt],
            'ends_at' => [$this->endsAt, $other->endsAt],
            'age_reference_date' => [$this->ageReferenceDate, $other->ageReferenceDate],
            'registration_opens_at' => [$this->registrationOpensAt, $other->registrationOpensAt],
            'registration_closes_at' => [$this->registrationClosesAt, $other->registrationClosesAt],
        ] as $field => [$mine, $theirs]) {
            if (self::instant($mine) !== self::instant($theirs)) {
                $changed[] = $field;
            }
        }
        if (array_map(static fn(Venue $v): array => $v->toArray(), $this->venues) !== array_map(static fn(Venue $v): array => $v->toArray(), $other->venues)) {
            $changed[] = 'venues';
        }

        return $changed;
    }

    private static function instant(?\DateTimeImmutable $value): ?int
    {
        return $value?->getTimestamp();
    }
}
