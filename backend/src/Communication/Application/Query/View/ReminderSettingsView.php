<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query\View;

final readonly class ReminderSettingsView
{
    /**
     * @param list<int> $paymentOffsetsHours hours relative to the due date (negative = before)
     */
    public function __construct(
        public array $paymentOffsetsHours,
        public int $preEventHours,
        public bool $custom,
    ) {}
}
