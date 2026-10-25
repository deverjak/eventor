<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\CapacityChanged;
use Eventor\Registration\Application\Capacity\AnnounceRaisedLimit\AnnounceRaisedLimit;
use Eventor\Registration\Application\Capacity\AvailabilityInvalidator;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `catalog.capacity_changed`: the public availability of the event is stale, and a raised limit may have freed
 * seats for the waitlist. Idempotent: invalidating twice and announcing twice are harmless.
 */
#[AsMessageHandler(fromTransport: 'registration_events')]
final readonly class ApplyCapacityChanged
{
    public function __construct(
        private AvailabilityInvalidator $availability,
        private CommandBus $commands,
    ) {}

    public function __invoke(CapacityChanged $event): void
    {
        $this->availability->forBuckets([$event->bucketId]);

        $raised = null !== $event->previousLimit && (null === $event->newLimit || $event->newLimit > $event->previousLimit);
        if ($raised) {
            $this->commands->dispatch(
                new AnnounceRaisedLimit($event->organizerId, $event->bucketId, $event->previousLimit, $event->newLimit),
                TenantStamp::of($event->organizerId),
            );
        }
    }
}
