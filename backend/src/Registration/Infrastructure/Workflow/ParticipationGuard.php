<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Workflow;

use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Contract\FinanceQuery;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Registration\Domain\Workflow\Transitions;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Audit\ActorType;
use Eventor\Shared\Domain\Finance\FinancialState;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * Guards of the `participation` state machine (chapter 05): the domain policies and the voters decide, the workflow
 * only asks.
 *
 *  - `restore` needs room (`CapacityAllocator::canReclaim`, S05: nothing is restored into a full bucket), the
 *    permission to resolve payment exceptions when a team member does it, and only the system restores an `expired`
 *    participation on its own (a person restores a flagged one from the queue);
 *  - `cancel` by a team member needs `registration.edit` (a participant is judged by the cancellation policy of the
 *    event in the command, a system actor is trusted);
 *  - `expire` needs the reservation to be over and the order not paid (a payment that was recorded a moment ago but
 *    not yet processed must not cost the participant the seat).
 */
#[AsGuardListener(workflow: 'participation')]
final readonly class ParticipationGuard
{
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private AuditContext $audit,
        private CapacityAllocator $capacity,
        private FinanceQuery $finance,
        private ClockInterface $clock,
    ) {}

    /**
     * @param GuardEvent<object> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof Participation) {
            return;
        }

        $user = ActorType::User === $this->audit->actor()?->type;
        match ($event->getTransition()->getName()) {
            'restore' => $this->guardRestore($event, $subject, $user),
            'cancel' => $user && !$this->authorization->isGranted(Permission::RegistrationEdit->value) ? $this->forbid($event) : null,
            'expire' => $this->guardExpire($event, $subject),
            default => null,
        };
    }

    /**
     * @param GuardEvent<object> $event
     */
    private function guardRestore(GuardEvent $event, Participation $participation, bool $user): void
    {
        if ($user) {
            if (!$this->authorization->isGranted(Permission::PaymentExceptionResolve->value)) {
                $this->forbid($event);

                return;
            }
            if (ParticipationState::Expired === $participation->state()) {
                $event->addTransitionBlocker(new TransitionBlocker('An expired participation is restored by the system when the late payment arrives.', 'restore_by_system_only'));

                return;
            }
        }

        // The automatic restore takes the seats first and says so; the manual one is judged on the released seats.
        if ($participation->hasTakenSeats()) {
            return;
        }

        if (!$this->capacity->canReclaim($participation->organizerId(), $participation->id())) {
            $event->addTransitionBlocker(new TransitionBlocker('There is no room left for this participation.', 'capacity_exhausted'));
        }
    }

    /**
     * @param GuardEvent<object> $event
     */
    private function guardExpire(GuardEvent $event, Participation $participation): void
    {
        $hold = $participation->holdExpiresAt();
        if (null === $hold || $hold > $this->clock->now()) {
            $event->addTransitionBlocker(new TransitionBlocker('The reservation has not expired yet.', 'hold_not_expired'));

            return;
        }

        $summary = $this->finance->summaries($participation->organizerId(), [$participation->orderId()])[$participation->orderId()->toString()] ?? null;
        if (null !== $summary && \in_array($summary->state, [FinancialState::Paid, FinancialState::Overpaid], true)) {
            $event->addTransitionBlocker(new TransitionBlocker('The order is already paid.', 'order_paid'));
        }
    }

    /**
     * @param GuardEvent<object> $event
     */
    private function forbid(GuardEvent $event): void
    {
        $event->addTransitionBlocker(new TransitionBlocker('You do not have the permission for this change.', Transitions::FORBIDDEN));
    }
}
