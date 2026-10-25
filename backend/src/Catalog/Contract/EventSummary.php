<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Display data of an event for messages, check-in and reports. No registration offer, no prices.
 */
final readonly class EventSummary
{
    /**
     * @param list<ActivitySummary> $activities
     */
    public function __construct(
        public EventId $eventId,
        public OrganizerId $organizerId,
        public string $slug,
        public string $name,
        public string $timezone,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public PublicationState $publicationState,
        public RegistrationState $registrationState,
        public ?VenueView $venue,
        public array $activities,
    ) {}
}
