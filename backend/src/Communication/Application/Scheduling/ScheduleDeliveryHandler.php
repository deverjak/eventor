<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Scheduling;

use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A redelivered integration event finds its delivery already planned and records nothing.')]
final readonly class ScheduleDeliveryHandler
{
    public function __construct(private DeliveryScheduler $scheduler) {}

    /**
     * @return array{deliveryId: string|null}
     */
    public function __invoke(ScheduleDelivery $command): array
    {
        return ['deliveryId' => $this->scheduler->schedule($command)?->id()->toString()];
    }
}
