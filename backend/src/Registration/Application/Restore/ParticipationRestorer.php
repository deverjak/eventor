<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Restore;

use Eventor\Registration\Contract\ConfirmationReason;
use Eventor\Registration\Contract\Exception\CapacityExhausted;
use Eventor\Registration\Contract\RestoredBy;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Registration\Domain\Order\Order;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Shared\Domain\Id\ParticipationId;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The restoration of participations (CAP-05, S06), shared by the manual action and the automatic one. Runs in the
 * transaction of the calling handler.
 *
 * `restore()` is the manual path: the `restore` transition first (its guard asks `canReclaim` while the seats are
 * still released, and checks the permission), then the seats are taken back as confirmed, which still fails with
 * `capacity_exhausted` if someone took the last one in between (the transaction rolls back).
 *
 * `restoreAll()` is the automatic path of a late payment: it takes the seats of all participations first (a failed
 * take is a 0-row UPDATE, the transaction stays healthy), gives them back if one participation does not fit and
 * answers `false` then, so the caller flags the lot for a human instead of restoring half an order.
 */
final readonly class ParticipationRestorer
{
    public function __construct(
        private WorkflowInterface $participationStateMachine,
        private CapacityAllocator $capacity,
    ) {}

    public function restore(Order $order, Participation $participation, RestoredBy $by, ?string $reason): void
    {
        $participation->transition($this->participationStateMachine, 'restore', $reason);
        $this->capacity->reclaim($participation->organizerId(), $participation->id(), ClaimKind::Confirmed, false);
        $order->noteRestored($participation->id(), $by);
        $order->noteConfirmed([$participation->id()], ConfirmationReason::Restored);
    }

    /**
     * @param list<Participation> $participations
     */
    public function restoreAll(Order $order, array $participations, RestoredBy $by): bool
    {
        $taken = [];
        foreach ($participations as $participation) {
            try {
                $this->capacity->reclaim($participation->organizerId(), $participation->id(), ClaimKind::Confirmed, false);
                $taken[] = $participation->id();
            } catch (CapacityExhausted) {
                $this->giveBack($order, $taken);

                return false;
            }
        }

        foreach ($participations as $participation) {
            $participation->seatsAlreadyTaken();
            $participation->transition($this->participationStateMachine, 'restore');
            $order->noteRestored($participation->id(), $by);
        }
        $order->noteConfirmed(array_map(static fn(Participation $p): ParticipationId => $p->id(), $participations), ConfirmationReason::Restored);

        return true;
    }

    /**
     * @param list<ParticipationId> $taken
     */
    private function giveBack(Order $order, array $taken): void
    {
        if ([] !== $taken) {
            $this->capacity->release($order->organizerId(), $taken);
        }
    }
}
