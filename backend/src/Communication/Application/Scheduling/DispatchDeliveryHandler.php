<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Scheduling;

use Eventor\Communication\Domain\Delivery\DeliveryRepository;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Queues an attempt of a delivery that already exists; the attempt records the events.')]
final readonly class DispatchDeliveryHandler
{
    public function __construct(
        private DeliveryRepository $deliveries,
        private DeliveryScheduler $scheduler,
        private ClockInterface $clock,
    ) {}

    public function __invoke(DispatchDelivery $command): bool
    {
        $delivery = $this->deliveries->findForUpdate(DeliveryId::fromString($command->deliveryId));
        if (null === $delivery || !$delivery->isDue(\DateTimeImmutable::createFromInterface($this->clock->now())) || $delivery->isDispatched()) {
            return false;
        }
        $this->scheduler->queue($delivery);

        return true;
    }
}
