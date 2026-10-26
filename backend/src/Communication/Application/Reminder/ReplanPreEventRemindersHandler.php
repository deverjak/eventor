<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Reminder;

use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Delivery\DeliveryRepository;
use Eventor\Communication\Domain\Reminder\ReminderSettings;
use Eventor\Communication\Domain\Reminder\ReminderSettingsRepository;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('An activity without waiting reminders changes nothing.')]
final readonly class ReplanPreEventRemindersHandler
{
    public function __construct(
        private DeliveryRepository $deliveries,
        private ReminderSettingsRepository $settings,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ReplanPreEventReminders $command): int
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $hours = $this->settings->current()?->preEventHours() ?? ReminderSettings::DEFAULT_PRE_EVENT_HOURS;
        $at = $command->newStartsAt->modify(\sprintf('-%d hours', $hours));

        $changed = 0;
        foreach ($this->deliveries->waitingForActivity(ActivityId::fromString($command->activityId), TemplateKey::PreEventReminder->value) as $delivery) {
            $delivery->replan($at, $now);
            ++$changed;
        }

        return $changed;
    }
}
