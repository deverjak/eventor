<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\RescheduleList;

/**
 * `catalog.activity_rescheduled` -> the list of that activity opens at the new start.
 */
final readonly class RescheduleCheckInList
{
    public function __construct(
        public string $activityId,
        public \DateTimeImmutable $newStartsAt,
    ) {}
}
