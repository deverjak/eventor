<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\EventPublished;
use Eventor\CheckIn\Application\ProvisionLists\ProvisionCheckInLists;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `catalog.event_published` -> a check-in list per activity (CHK-01). Idempotent: the lists that exist are kept.
 */
#[AsMessageHandler(fromTransport: 'checkin_events')]
final readonly class OnEventPublished
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(EventPublished $event): void
    {
        $this->commands->dispatch(new ProvisionCheckInLists(
            $event->eventId->toString(),
            array_map(static fn($id): string => $id->toString(), $event->activityIds),
        ), TenantStamp::of($event->organizerId));
    }
}
