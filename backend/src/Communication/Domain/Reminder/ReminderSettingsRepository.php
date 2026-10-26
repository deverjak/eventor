<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Reminder;

interface ReminderSettingsRepository
{
    public function add(ReminderSettings $settings): void;

    /** The settings of the current organizer (the tenant filter scopes it), or null when the defaults apply. */
    public function current(): ?ReminderSettings;
}
