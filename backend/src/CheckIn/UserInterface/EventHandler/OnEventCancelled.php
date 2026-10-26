<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\EventCancelled;
use Eventor\CheckIn\Application\CancelLists\CancelCheckInLists;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `catalog.event_cancelled` -> the lists of the event stop admitting people.
 */
#[AsMessageHandler(fromTransport: 'checkin_events')]
final readonly class OnEventCancelled
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(EventCancelled $event): void
    {
        $this->commands->dispatch(new CancelCheckInLists($event->eventId->toString()), TenantStamp::of($event->organizerId));
    }
}
