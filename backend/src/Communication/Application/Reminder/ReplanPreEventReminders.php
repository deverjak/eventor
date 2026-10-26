<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Reminder;

/**
 * An activity moved: the reminders that still wait follow its new start.
 */
final readonly class ReplanPreEventReminders
{
    public function __construct(
        public string $activityId,
        public \DateTimeImmutable $newStartsAt,
    ) {}
}
