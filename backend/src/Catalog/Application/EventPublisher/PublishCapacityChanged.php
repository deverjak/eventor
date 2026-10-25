<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\EventPublisher;

use Eventor\Catalog\Contract\Event\CapacityChanged;
use Eventor\Catalog\Domain\Event\CapacityLimitChanged;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A changed limit of an existing bucket -> `catalog.capacity_changed` (CAP-06). Before the publication no bucket exists,
 * so a draft emits nothing.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishCapacityChanged
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CapacityLimitChanged $event): void
    {
        if (!$event->live) {
            return;
        }
        $this->eventBus->dispatch(
            new CapacityChanged($event->organizerId, $event->bucketId, $event->scope, $event->previousLimit, $event->newLimit, $event->belowOccupancy, \DateTimeImmutable::createFromInterface($this->clock->now())),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
