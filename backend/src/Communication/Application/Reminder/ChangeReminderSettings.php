<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Reminder;

final readonly class ChangeReminderSettings
{
    /**
     * @param list<int> $paymentOffsetsHours hours relative to the due date of a payment request (negative = before)
     */
    public function __construct(
        public array $paymentOffsetsHours,
        public int $preEventHours,
    ) {}
}
