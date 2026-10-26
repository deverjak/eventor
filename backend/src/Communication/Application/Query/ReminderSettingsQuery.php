<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Communication\Application\Query\View\ReminderSettingsView;
use Eventor\Communication\Domain\Reminder\ReminderSettings;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The reminder schedule of an organizer, or the defaults while it has not changed it.
 */
final readonly class ReminderSettingsQuery
{
    public function __construct(private Connection $connection) {}

    public function get(OrganizerId $organizer): ReminderSettingsView
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT payment_offsets_hours, pre_event_hours FROM communication.reminder_settings WHERE organizer_id = :organizer',
            ['organizer' => $organizer->toString()],
        );
        if (false === $row) {
            return new ReminderSettingsView(ReminderSettings::DEFAULT_PAYMENT_OFFSETS, ReminderSettings::DEFAULT_PRE_EVENT_HOURS, false);
        }

        $offsets = array_values(array_map(static fn(mixed $hours): int => \is_int($hours) ? $hours : 0, Row::json($row, 'payment_offsets_hours')));

        return new ReminderSettingsView($offsets, Row::int($row, 'pre_event_hours'), true);
    }
}
