<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\EventPublisher;

use Eventor\Communication\Contract\Event\DeliveryFailed;
use Eventor\Communication\Domain\Event\DeliveryFailedPermanently;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates the domain event into `communication.delivery_failed` (Attention opens one item per campaign).
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishDeliveryFailed
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(DeliveryFailedPermanently $event): void
    {
        $this->eventBus->dispatch(
            new DeliveryFailed($event->organizerId, $event->deliveryId, $event->campaignId, $event->trigger, $event->error, \DateTimeImmutable::createFromInterface($this->clock->now())),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
