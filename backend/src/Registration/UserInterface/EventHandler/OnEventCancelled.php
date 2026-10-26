<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\EventCancelled;
use Eventor\Registration\Application\Cancellation\CancelEventOrdersBatch;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Audit\AuditActor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `catalog.event_cancelled` (EVT-12): cancels the orders of the event in batches of 100, each batch its own
 * transaction. A redelivery finds no active participation and stops at the first batch.
 */
#[AsMessageHandler(fromTransport: 'registration_events')]
final readonly class OnEventCancelled
{
    public function __construct(
        private CommandBus $commands,
        private AuditContext $audit,
    ) {}

    public function __invoke(EventCancelled $event): void
    {
        $this->audit->as(AuditActor::system('registration.cancel_orders_on_event_cancelled'), function () use ($event): void {
            do {
                $handled = $this->commands->dispatch(new CancelEventOrdersBatch($event->organizerId, $event->eventId, $event->reason), TenantStamp::of($event->organizerId));
            } while (\is_int($handled) && $handled > 0);
        });
    }
}
