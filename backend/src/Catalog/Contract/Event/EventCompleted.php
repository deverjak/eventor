<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Event;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * The event is over (`ends_at` passed): post-event messages and the retention clock may start.
 *
 * P0. Planned subscribers: communication, privacy.
 * Note: `privacy.schedule_retention` is P1.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'catalog.event_completed', version: 1)]
#[PendingSubscribers('communication', 'privacy')]
final readonly class EventCompleted
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
