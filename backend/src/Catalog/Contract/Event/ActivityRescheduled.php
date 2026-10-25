<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Event;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * The start of an activity moved. `important` = the organizer wants affected participants to be told (S12).
 *
 * P0. Planned subscribers: communication, checkin.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'catalog.activity_rescheduled', version: 1)]
#[PendingSubscribers('communication', 'checkin')]
final readonly class ActivityRescheduled
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public ActivityId $activityId,
        public \DateTimeImmutable $previousStartsAt,
        public \DateTimeImmutable $newStartsAt,
        public bool $important,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
