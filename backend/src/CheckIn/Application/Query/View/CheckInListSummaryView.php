<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query\View;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * A check-in list with its live counters (CHK-01): `expected` = confirmed participations of the activity.
 */
final readonly class CheckInListSummaryView
{
    public function __construct(
        public CheckInListId $id,
        public EventId $eventId,
        public ActivityId $activityId,
        public string $name,
        public ?\DateTimeImmutable $opensAt,
        public int $expected,
        public int $checkedIn,
    ) {}
}
