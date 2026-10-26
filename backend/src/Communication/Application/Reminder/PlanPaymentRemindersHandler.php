<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Reminder;

use Eventor\Communication\Application\Scheduling\DeliveryScheduler;
use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Reminder\ReminderSettings;
use Eventor\Communication\Domain\Reminder\ReminderSettingsRepository;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Past or already planned reminders create nothing; new ones record DeliveryChanged through the delivery.')]
final readonly class PlanPaymentRemindersHandler
{
    public function __construct(
        private ReminderSettingsRepository $settings,
        private DeliveryScheduler $scheduler,
        private ClockInterface $clock,
    ) {}

    public function __invoke(PlanPaymentReminders $command): int
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $offsets = $this->settings->current()?->paymentOffsetsHours() ?? ReminderSettings::DEFAULT_PAYMENT_OFFSETS;

        $planned = 0;
        foreach ($offsets as $hours) {
            $at = $command->dueAt->modify(\sprintf('%+d hours', $hours));
            if ($at <= $now) {
                continue;
            }
            $delivery = $this->scheduler->schedule(new ScheduleDelivery(
                TemplateKey::PaymentReminder,
                \sprintf('payment_reminder:%s:%d', $command->paymentRequestId, $hours),
                orderId: $command->orderId,
                refId: $command->paymentRequestId,
                sendAfter: $at,
            ));
            $planned += null === $delivery ? 0 : 1;
        }

        return $planned;
    }
}
