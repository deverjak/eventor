<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\EventPublisher;

use Eventor\Catalog\Contract\Event\ActivityRescheduled;
use Eventor\Catalog\Domain\Event\ActivityTimeChanged;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A moved start of a published activity -> `catalog.activity_rescheduled` (S12). Giving an activity its first start
 * time is not a reschedule: nobody was told a time before.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishActivityRescheduled
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ActivityTimeChanged $event): void
    {
        if (!$event->live || null === $event->previousStartsAt || null === $event->newStartsAt) {
            return;
        }
        $this->eventBus->dispatch(
            new ActivityRescheduled($event->organizerId, $event->eventId, $event->activityId, $event->previousStartsAt, $event->newStartsAt, $event->important, \DateTimeImmutable::createFromInterface($this->clock->now())),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
