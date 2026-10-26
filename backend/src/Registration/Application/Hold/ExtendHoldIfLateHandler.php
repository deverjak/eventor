<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Hold;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Instructions that came in time leave the reservation alone; an extension records ParticipationChanged.')]
final readonly class ExtendHoldIfLateHandler
{
    public function __construct(
        private OrderRepository $orders,
        private ParticipationRepository $participations,
        private EventCatalog $catalog,
    ) {}

    public function __invoke(ExtendHoldIfLate $command): void
    {
        try {
            $order = $this->orders->get($command->orderId);
            $closes = $this->catalog->registrationSnapshot($command->organizerId, $order->eventId())->registrationClosesAt;
        } catch (OrderNotFound|EventNotFound) {
            return;
        }

        $until = $command->issuedAt->modify('+'.ExtendHoldIfLate::MINIMUM_HOURS.' hours');
        if (null !== $closes && $closes < $until) {
            $until = $closes;
        }

        foreach ($this->participations->ofOrder($command->orderId) as $participation) {
            $participation->extendHold($until);
        }
    }
}
