<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Event;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * The owner cancelled a published event (EVT-12). Starts the cancellation process in Registration. `reason` is the organizer-written text shown to participants; it must not name individuals.
 *
 * P0. Planned subscribers: registration, communication, checkin.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'catalog.event_cancelled', version: 1)]
#[PendingSubscribers('registration', 'communication', 'checkin')]
final readonly class EventCancelled
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public string $reason,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
