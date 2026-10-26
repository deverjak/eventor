<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Reminder;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Communication\Application\Scheduling\DeliveryScheduler;
use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Reminder\ReminderSettings;
use Eventor\Communication\Domain\Reminder\ReminderSettingsRepository;
use Eventor\Registration\Contract\OrderQuery;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Activities that already started or are already planned create nothing; new reminders record DeliveryChanged through the delivery.')]
final readonly class PlanPreEventRemindersHandler
{
    public function __construct(
        private OrderQuery $orders,
        private EventCatalog $events,
        private ReminderSettingsRepository $settings,
        private DeliveryScheduler $scheduler,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    public function __invoke(PlanPreEventReminders $command): int
    {
        $organizer = $this->tenant->require();
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $hours = $this->settings->current()?->preEventHours() ?? ReminderSettings::DEFAULT_PRE_EVENT_HOURS;

        $order = $this->orders->get($organizer, OrderId::fromString($command->orderId));
        $confirmed = [];
        foreach ($order->participations as $participation) {
            if (\in_array($participation->id->toString(), $command->participationIds, true)) {
                $confirmed[$participation->activityId->toString()] = $participation->activityId;
            }
        }
        if ([] === $confirmed) {
            return 0;
        }

        $event = $this->events->summary($organizer, $order->eventId);
        $startsAt = [];
        foreach ($event->activities as $activity) {
            $startsAt[$activity->activityId->toString()] = $activity->startsAt ?? $event->startsAt;
        }

        $planned = 0;
        foreach ($confirmed as $activityKey => $activityId) {
            $start = $startsAt[$activityKey] ?? null;
            if (null === $start || $start <= $now) {
                continue;
            }
            $delivery = $this->scheduler->schedule(new ScheduleDelivery(
                TemplateKey::PreEventReminder,
                \sprintf('pre_event:%s:%s', $command->orderId, $activityKey),
                eventId: $order->eventId->toString(),
                orderId: $command->orderId,
                activityId: $activityKey,
                sendAfter: \DateTimeImmutable::createFromInterface($start)->modify(\sprintf('-%d hours', $hours)),
            ));
            $planned += null === $delivery ? 0 : 1;
        }

        return $planned;
    }
}
