<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Reminder;

use Eventor\Communication\Domain\Reminder\ReminderSettings;
use Eventor\Communication\Domain\Reminder\ReminderSettingsRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ChangeReminderSettingsHandler
{
    public function __construct(
        private ReminderSettingsRepository $settings,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ChangeReminderSettings $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $current = $this->settings->current();
        if (null === $current) {
            $this->settings->add(ReminderSettings::create($this->tenant->require(), $command->paymentOffsetsHours, $command->preEventHours, $now));

            return;
        }
        $current->change($command->paymentOffsetsHours, $command->preEventHours, $now);
    }
}
