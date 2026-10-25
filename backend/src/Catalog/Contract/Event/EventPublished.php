<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Event;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * An event went from draft to published (EVT-03): the form version v1 and the capacity buckets exist.
 *
 * P0. Planned subscribers: reporting, catalog, checkin.
 * Note: `checkin` (default check-in list per activity) is not in the catalogue table; added because nothing else creates the lists.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'catalog.event_published', version: 1)]
#[PendingSubscribers('reporting', 'catalog', 'checkin')]
final readonly class EventPublished
{
    /**
     * @param list<ActivityId> $activityIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public string $slug,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public array $activityIds,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
