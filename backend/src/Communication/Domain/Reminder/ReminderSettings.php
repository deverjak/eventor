<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Reminder;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Communication\Domain\Event\ReminderSettingsChanged;
use Eventor\Communication\Domain\Exception\InvalidReminderSettings;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * The reminder schedule of an organizer (PAY-09, COM-01). `paymentOffsetsHours` are hours relative to the due date of a
 * payment request (negative = before); `preEventHours` is how long before an activity starts the reminder goes out.
 * Without a row the defaults apply.
 */
#[ORM\Entity]
#[ORM\Table(name: 'reminder_settings', schema: 'communication')]
class ReminderSettings implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    public const array DEFAULT_PAYMENT_OFFSETS = [-72, -24];
    public const int DEFAULT_PRE_EVENT_HOURS = 24;
    public const int MAX_OFFSETS = 5;

    /**
     * @param list<int> $paymentOffsetsHours
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(name: 'payment_offsets_hours', type: 'json', options: ['jsonb' => true])]
        private array $paymentOffsetsHours,
        #[ORM\Column(type: 'integer')]
        private int $preEventHours,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param list<int> $paymentOffsetsHours
     */
    public static function create(OrganizerId $organizerId, array $paymentOffsetsHours, int $preEventHours, \DateTimeImmutable $now): self
    {
        $settings = new self($organizerId, self::normalise($paymentOffsetsHours), self::guardHours($preEventHours), $now);
        $settings->record(new ReminderSettingsChanged($organizerId, $settings->paymentOffsetsHours, $preEventHours));

        return $settings;
    }

    /**
     * @param list<int> $paymentOffsetsHours
     */
    public function change(array $paymentOffsetsHours, int $preEventHours, \DateTimeImmutable $now): void
    {
        $this->paymentOffsetsHours = self::normalise($paymentOffsetsHours);
        $this->preEventHours = self::guardHours($preEventHours);
        $this->updatedAt = $now;
        $this->record(new ReminderSettingsChanged($this->organizerId, $this->paymentOffsetsHours, $this->preEventHours));
    }

    /**
     * @param list<int> $offsets
     *
     * @return list<int>
     */
    private static function normalise(array $offsets): array
    {
        if (\count($offsets) > self::MAX_OFFSETS) {
            throw InvalidReminderSettings::because(\sprintf('At most %d payment reminders can be scheduled.', self::MAX_OFFSETS));
        }
        foreach ($offsets as $hours) {
            if ($hours < -720 || $hours > 720) {
                throw InvalidReminderSettings::because('A payment reminder goes out at most 30 days before or after the due date.');
            }
        }
        $unique = array_values(array_unique($offsets));
        sort($unique);

        return $unique;
    }

    private static function guardHours(int $hours): int
    {
        if ($hours < 1 || $hours > 336) {
            throw InvalidReminderSettings::because('The pre-event reminder goes out 1 to 336 hours before the activity.');
        }

        return $hours;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    /**
     * @return list<int>
     */
    public function paymentOffsetsHours(): array
    {
        return $this->paymentOffsetsHours;
    }

    public function preEventHours(): int
    {
        return $this->preEventHours;
    }
}
