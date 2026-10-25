<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\EventPublisher;

use Eventor\Identity\Contract\Event\PairrotConnected;
use Eventor\Identity\Domain\Event\PairrotConnectedToOrganizer;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * `PairrotConnectedToOrganizer` -> `identity.pairrot_connected` (Catalog may now enable transfer payments).
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishPairrotConnected
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(PairrotConnectedToOrganizer $event): void
    {
        $this->eventBus->dispatch(
            new PairrotConnected($event->organizerId, $event->subtenantId, $event->bankConnectionId, $this->clock->now()),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
