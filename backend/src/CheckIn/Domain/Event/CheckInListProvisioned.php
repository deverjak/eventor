<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Event;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the check-in list of an activity was created (reaction to `catalog.event_published`).
 */
final readonly class CheckInListProvisioned
{
    public function __construct(
        public OrganizerId $organizerId,
        public CheckInListId $listId,
        public EventId $eventId,
        public ActivityId $activityId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
