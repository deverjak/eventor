<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Capacity;

use Eventor\Registration\Contract\CapacityReleaseCause;
use Eventor\Registration\Contract\Event\CapacityReleased;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Capacity\ReleasedCapacity;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Release plus announcement, for the handlers of cancel / expire / variant change: gives the seats back through the
 * allocator and publishes `registration.capacity_released` (drives the waitlist and the availability cache). Called
 * from a handler already on `command.bus`; the integration event goes into the outbox in the same transaction.
 */
final readonly class CapacityReleaser
{
    public function __construct(
        private CapacityAllocator $allocator,
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    /**
     * @param list<ParticipationId> $participations
     */
    public function release(OrganizerId $organizer, array $participations, CapacityReleaseCause $cause): ReleasedCapacity
    {
        $released = $this->allocator->release($organizer, $participations);
        $this->announce($organizer, $released, $cause);

        return $released;
    }

    /**
     * Publishes the event for seats that became free; nothing is published when nothing was freed.
     */
    public function announce(OrganizerId $organizer, ReleasedCapacity $released, CapacityReleaseCause $cause): void
    {
        if ($released->isEmpty() || $released->seats < 1) {
            return;
        }

        $this->eventBus->dispatch(
            new CapacityReleased($organizer, $released->bucketIds, $released->seats, $cause, \DateTimeImmutable::createFromInterface($this->clock->now())),
            [TenantStamp::of($organizer)],
        );
    }
}
