<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\EventPublisher;

use Eventor\Catalog\Contract\Event\RegistrationStateChanged;
use Eventor\Catalog\Domain\Event\RegistrationWindowChanged;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * `registration_window` transition -> `catalog.registration_state_changed` (EVT-04).
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishRegistrationStateChanged
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RegistrationWindowChanged $event): void
    {
        $this->eventBus->dispatch(
            new RegistrationStateChanged($event->organizerId, $event->eventId, $event->activityId, $event->from, $event->to, \DateTimeImmutable::createFromInterface($this->clock->now())),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
