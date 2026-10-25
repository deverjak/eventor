<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Event;

use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * Registration of the event (activityId = null) or of one activity opened, paused, resumed or closed (EVT-04).
 *
 * P0. Planned subscribers: catalog, registration.
 * Note: `registration.notify_waitlist_closed` is P1.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'catalog.registration_state_changed', version: 1)]
#[PendingSubscribers('catalog', 'registration')]
final readonly class RegistrationStateChanged
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public ?ActivityId $activityId,
        public RegistrationState $from,
        public RegistrationState $to,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
