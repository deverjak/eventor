<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * A check-in list. Defaults to one list per activity; `opensAt` follows `catalog.activity_rescheduled`.
 */
final readonly class CheckInListView
{
    public function __construct(
        public CheckInListId $id,
        public EventId $eventId,
        public ActivityId $activityId,
        public string $name,
        public ?\DateTimeImmutable $opensAt,
    ) {}
}
