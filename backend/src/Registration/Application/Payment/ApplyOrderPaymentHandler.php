<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Payment;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Registration\Application\Restore\ParticipationRestorer;
use Eventor\Registration\Contract\AttentionCause;
use Eventor\Registration\Contract\ConfirmationReason;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Contract\RestoredBy;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Order\Order;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * What a change of the financial state does to the participations (chapter 04, path 4; S04-S07):
 *
 *  - `paid` / `overpaid`: pending ones are confirmed (`held -> confirmed`); expired ones become a late payment (S05):
 *    restored at once when the event allows it and every seat is free (S06), otherwise flagged `requires_attention`;
 *  - `partially_paid`: pending ones keep their reservation (S04); expired ones are flagged, never restored;
 *  - money for an order whose participations are all cancelled is reported to Attention (the refund task is Payments').
 *
 * Idempotent through the state machine: a repeated event finds nothing in the source state.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('An unpaid or refunded state, or a repeated event, changes nothing; every real change records its own events.')]
final readonly class ApplyOrderPaymentHandler
{
    public function __construct(
        private OrderRepository $orders,
        private ParticipationRepository $participations,
        private WorkflowInterface $participationStateMachine,
        private CapacityAllocator $capacity,
        private ParticipationRestorer $restorer,
        private EventCatalog $catalog,
    ) {}

    public function __invoke(ApplyOrderPayment $command): void
    {
        $paid = \in_array($command->state, [FinancialState::Paid, FinancialState::Overpaid], true);
        if (!$paid && FinancialState::PartiallyPaid !== $command->state) {
            return;
        }

        try {
            $order = $this->orders->get($command->orderId);
        } catch (OrderNotFound) {
            return; // an event of an order this module does not know (stale or foreign)
        }

        $participations = $this->participations->ofOrder($command->orderId);
        $reason = match ($order->paymentMethod()) {
            PaymentMethod::Cash => ConfirmationReason::Cash,
            PaymentMethod::Free => ConfirmationReason::Free,
            PaymentMethod::BankTransfer => ConfirmationReason::Paid,
        };

        $confirmed = [];
        $late = [];
        foreach ($participations as $participation) {
            if ($paid && ParticipationState::Pending === $participation->state()) {
                $participation->transition($this->participationStateMachine, 'confirm', null, []);
                $this->capacity->confirm($participation->organizerId(), $participation->id());
                $confirmed[] = $participation->id();
            } elseif (ParticipationState::Expired === $participation->state()) {
                $late[] = $participation;
            }
        }
        $order->noteConfirmed($confirmed, $reason);

        if ([] !== $late) {
            $this->handleLatePayment($order, $late, $paid);
        }

        if ($command->positiveDelta && [] === array_filter($participations, static fn(Participation $p): bool => $p->isActive() || ParticipationState::Expired === $p->state())) {
            foreach ($participations as $participation) {
                if (ParticipationState::Cancelled === $participation->state()) {
                    $order->noteNeedsAttention($participation->id(), AttentionCause::PaymentOnCancelled);
                }
            }
        }
    }

    /**
     * @param list<Participation> $late
     */
    private function handleLatePayment(Order $order, array $late, bool $paid): void
    {
        $organizer = $order->organizerId();
        $ids = array_map(static fn(Participation $p): ParticipationId => $p->id(), $late);

        $autoRestore = $paid && $this->autoRestoreEnabled($order);
        $room = true;
        foreach ($late as $participation) {
            $room = $room && $this->capacity->canReclaim($organizer, $participation->id());
        }

        $restored = $autoRestore && $room && $this->restorer->restoreAll($order, $late, RestoredBy::System);
        // With auto-restore on, "capacity available" means "and it was restored" (Attention stays quiet only then).
        $order->noteLatePayment($ids, $autoRestore ? $restored : $room, $autoRestore);
        if ($restored) {
            return;
        }

        foreach ($late as $participation) {
            $participation->transition($this->participationStateMachine, 'flag_late_payment', 'Money arrived after the reservation expired');
            $order->noteNeedsAttention($participation->id(), AttentionCause::LatePayment);
        }
    }

    private function autoRestoreEnabled(Order $order): bool
    {
        try {
            return $this->catalog->registrationSnapshot($order->organizerId(), $order->eventId())->autoRestoreOnLatePayment;
        } catch (EventNotFound) {
            return false;
        }
    }
}
