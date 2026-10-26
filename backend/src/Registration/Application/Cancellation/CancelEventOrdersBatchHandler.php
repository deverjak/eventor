<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Cancellation;

use Doctrine\DBAL\Connection;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Registration\Contract\OrderAmountChangeReason;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Cancels the participations of the next orders of a cancelled event as the system (reason = the reason of the
 * event) and waives their amount due, which Payments turns into refund tasks for paid orders and cancelled requests
 * for unpaid ones. Returns how many orders it handled; the subscriber repeats until the result is 0.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A step with no active participation left changes nothing; every cancellation it does record is an event of its own.')]
final readonly class CancelEventOrdersBatchHandler
{
    public function __construct(
        private Connection $connection,
        private OrderRepository $orders,
        private ParticipationRepository $participations,
        private ParticipationCanceller $canceller,
    ) {}

    public function __invoke(CancelEventOrdersBatch $command): int
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT order_id FROM registration.participation
              WHERE organizer_id = :organizer AND event_id = :event AND state IN ('pending', 'confirmed', 'requires_attention')
              ORDER BY order_id LIMIT :limit",
            ['organizer' => $command->organizerId->toString(), 'event' => $command->eventId->toString(), 'limit' => $command->limit],
        );

        foreach ($ids as $id) {
            $orderId = OrderId::fromString($id);
            $this->canceller->cancel(
                $this->orders->get($orderId),
                $this->participations->ofOrder($orderId),
                CancelledBy::System,
                $command->reason,
                OrderAmountChangeReason::EventCancelled,
                true,
            );
        }

        return \count($ids);
    }
}
