<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Event;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * Public details of a published event changed (name, venue, description, dates...). `changedFields` are property names, never values.
 *
 * P0. Planned subscribers: catalog.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'catalog.event_details_changed', version: 1)]
#[PendingSubscribers('catalog')]
final readonly class EventDetailsChanged
{
    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public array $changedFields,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
