<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\EventPublisher;

use Eventor\Legal\Contract\Event\ReconfirmationRequested;
use Eventor\Legal\Domain\Event\ReconfirmationNeeded;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates the domain event into `legal.reconfirmation_requested`.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishReconfirmationRequested
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ReconfirmationNeeded $event): void
    {
        $this->eventBus->dispatch(
            new ReconfirmationRequested(
                $event->organizerId,
                $event->requirementId,
                $event->versionId,
                $event->participationIds,
                $event->orderIds,
                $this->clock->now(),
            ),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
