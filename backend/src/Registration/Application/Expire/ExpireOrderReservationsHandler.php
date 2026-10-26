<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Expire;

use Eventor\Registration\Application\Capacity\CapacityReleaser;
use Eventor\Registration\Contract\CapacityReleaseCause;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Domain\Exception\ParticipationTransitionNotAllowed;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * `pending` -> `expired` for every participation of the order whose reservation is over (the guard checks the clock and
 * that the order is not paid), seats back in the buckets, `registration.participation_expired` and
 * `registration.capacity_released`. The payment request in Pairrot is left alone on purpose: a late payment must still
 * match and lead to S05 / S06. A participation the guard refuses is skipped; a second run finds nothing to do.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Nothing to expire (a repeated sweep, or the order was paid meanwhile); each real expiry records its own events.')]
final readonly class ExpireOrderReservationsHandler
{
    public function __construct(
        private ParticipationRepository $participations,
        private OrderRepository $orders,
        private WorkflowInterface $participationStateMachine,
        private CapacityReleaser $releaser,
    ) {}

    public function __invoke(ExpireOrderReservations $command): int
    {
        $order = $this->orders->get($command->orderId);
        $expired = 0;
        foreach ($this->participations->ofOrder($command->orderId) as $participation) {
            if (ParticipationState::Pending !== $participation->state()) {
                continue;
            }
            try {
                $participation->transition($this->participationStateMachine, 'expire', 'The reservation expired');
            } catch (ParticipationTransitionNotAllowed) {
                continue; // not over yet, or paid in the meantime
            }

            $released = $this->releaser->release($command->organizerId, [$participation->id()], CapacityReleaseCause::Expired);
            $order->noteExpired($participation->id(), $released->bucketIds);
            ++$expired;
        }

        return $expired;
    }
}
