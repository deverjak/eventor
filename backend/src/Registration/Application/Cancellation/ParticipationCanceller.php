<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Cancellation;

use Eventor\Registration\Application\Capacity\CapacityReleaser;
use Eventor\Registration\Contract\CancellationReason;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Registration\Contract\CapacityReleaseCause;
use Eventor\Registration\Contract\OrderAmountChangeReason;
use Eventor\Registration\Domain\Order\Order;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The cancellation shared by the participant, the organizer and the event cancellation (chapter 04, path 6): the
 * `cancel` transition (its guards decide who may), the seats back in the buckets (`registration.capacity_released`),
 * the QR code dead (`qr_version++`) and the amount due of the order reduced by the price of what was cancelled
 * (`registration.order_amount_changed`, Payments turns a surplus into a refund task). Runs in the transaction of the
 * calling handler.
 */
final readonly class ParticipationCanceller
{
    public function __construct(
        private WorkflowInterface $participationStateMachine,
        private CapacityReleaser $releaser,
        private ClockInterface $clock,
    ) {}

    /**
     * Cancels the active participations among `$participations`; the others are left alone (a repeated event
     * cancellation changes nothing).
     *
     * @param list<Participation> $participations
     * @param bool                $waiveAmount    the whole order is void (event cancelled): the amount due becomes zero
     *
     * @return list<ParticipationId> the cancelled ones
     */
    public function cancel(Order $order, array $participations, CancelledBy $by, string $reason, OrderAmountChangeReason $amountReason, bool $waiveAmount = false): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $cancelled = [];
        $refunded = Money::zero($order->amountDue()->currency);

        foreach ($participations as $participation) {
            if (!$participation->isActive()) {
                continue;
            }
            $participation->cancel($this->participationStateMachine, $by, $reason, $now);
            $this->releaser->release($participation->organizerId(), [$participation->id()], CapacityReleaseCause::Cancelled);
            $order->noteCancelled($participation->id(), $by, match (true) {
                OrderAmountChangeReason::EventCancelled === $amountReason => CancellationReason::EventCancelled,
                CancelledBy::Participant === $by => CancellationReason::ParticipantRequest,
                CancelledBy::Organizer === $by => CancellationReason::Organizer,
                default => CancellationReason::Other,
            });
            $refunded = $refunded->add($participation->price());
            $cancelled[] = $participation->id();
        }

        if ([] !== $cancelled) {
            $order->changeAmountDue($waiveAmount ? Money::zero($order->amountDue()->currency) : $order->amountDue()->subtract($refunded), $amountReason);
        }

        return $cancelled;
    }
}
