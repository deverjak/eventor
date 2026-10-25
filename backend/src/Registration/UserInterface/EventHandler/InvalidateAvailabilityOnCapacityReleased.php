<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\EventHandler;

use Eventor\Registration\Application\Capacity\AvailabilityInvalidator;
use Eventor\Registration\Contract\Event\CapacityReleased;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `registration.capacity_released`: seats came back, so the cached availability of the event must go. Nothing is
 * written to the database; the waitlist (P1) will add its own handler for the same event.
 */
#[AsMessageHandler(fromTransport: 'registration_events')]
final readonly class InvalidateAvailabilityOnCapacityReleased
{
    public function __construct(private AvailabilityInvalidator $availability) {}

    public function __invoke(CapacityReleased $event): void
    {
        $this->availability->forBuckets($event->bucketIds);
    }
}
