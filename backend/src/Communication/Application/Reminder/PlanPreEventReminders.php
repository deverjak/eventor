<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Reminder;

/**
 * COM-01: one reminder per order and activity, a configured number of hours before the activity starts.
 */
final readonly class PlanPreEventReminders
{
    /**
     * @param list<string> $participationIds the participations that were just confirmed
     */
    public function __construct(
        public string $orderId,
        public array $participationIds,
    ) {}
}
