<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Registration\Domain;

use Eventor\Registration\Contract\CancelledBy;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Domain\Event\ParticipationChanged;
use Eventor\Registration\Domain\Exception\ParticipationTransitionNotAllowed;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;

/**
 * The participation state machine of chapter 05 without a kernel: transitions, the reservation that lives only while
 * `pending`, the QR version that dies with a cancellation, the audit event of every change.
 */
final class ParticipationLifecycleTest extends TestCase
{
    private const NOW = '2026-11-01 12:00:00+01:00';

    private function machine(): StateMachine
    {
        $transitions = [
            new Transition('confirm', 'pending', 'confirmed'),
            new Transition('expire', 'pending', 'expired'),
            new Transition('restore', ['expired', 'requires_attention'], 'confirmed'),
            new Transition('flag_late_payment', 'expired', 'requires_attention'),
            new Transition('cancel', ['pending', 'confirmed', 'requires_attention'], 'cancelled'),
        ];
        $flat = [];
        foreach ($transitions as $t) {
            $to = $t->getTos()[0] ?? throw new \LogicException('A transition must have a target place.');
            foreach ($t->getFroms() as $from) {
                \assert(\is_string($from) && \is_string($to));
                $flat[] = new Transition($t->getName(), $from, $to);
            }
        }

        return new StateMachine(new Definition(['pending', 'confirmed', 'expired', 'requires_attention', 'cancelled'], $flat, 'pending'), new MethodMarkingStore(true, 'state'));
    }

    private function participation(bool $held): Participation
    {
        return Participation::create(
            ParticipationId::generate(),
            OrganizerId::generate(),
            OrderId::generate(),
            PersonId::generate(),
            EventId::generate(),
            ActivityId::generate(),
            VariantId::generate(),
            null,
            $held ? new \DateTimeImmutable('2026-11-03 12:00:00+01:00') : null,
            'ABCD2345',
            null,
            Money::of(30000),
            new \DateTimeImmutable(self::NOW),
        );
    }

    public function test_a_cash_or_free_participation_starts_confirmed_a_transfer_one_pending_with_a_hold(): void
    {
        self::assertSame(ParticipationState::Confirmed, $this->participation(false)->state());
        self::assertNull($this->participation(false)->holdExpiresAt());

        $pending = $this->participation(true);
        self::assertSame(ParticipationState::Pending, $pending->state());
        self::assertNotNull($pending->holdExpiresAt());
    }

    public function test_confirming_ends_the_reservation_and_leaves_an_audit_event(): void
    {
        $participation = $this->participation(true);

        $participation->transition($this->machine(), 'confirm');

        self::assertSame(ParticipationState::Confirmed, $participation->state());
        self::assertNull($participation->holdExpiresAt(), 'the reservation exists only while pending');
        $events = $participation->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(ParticipationChanged::class, $event);
        self::assertSame('participation.state.confirm', $event->auditChange()->action);
        self::assertSame(['state' => 'pending'], $event->auditChange()->before);
    }

    public function test_the_late_payment_path_expire_flag_restore(): void
    {
        $participation = $this->participation(true);
        $machine = $this->machine();

        $participation->transition($machine, 'expire');
        $participation->transition($machine, 'flag_late_payment');
        self::assertSame(ParticipationState::RequiresAttention, $participation->state());
        $participation->transition($machine, 'restore', 'paid');

        self::assertSame(ParticipationState::Confirmed, $participation->state());
    }

    public function test_cancelling_bumps_the_qr_version_and_remembers_who_and_why(): void
    {
        $participation = $this->participation(false);

        $participation->cancel($this->machine(), CancelledBy::Organizer, 'Telefonicky', new \DateTimeImmutable(self::NOW));

        self::assertSame(ParticipationState::Cancelled, $participation->state());
        self::assertSame(2, $participation->qrVersion());
        self::assertSame(CancelledBy::Organizer, $participation->cancelledBy());
        self::assertFalse($participation->isActive());
    }

    public function test_a_transition_from_the_wrong_state_is_refused_as_a_conflict(): void
    {
        $participation = $this->participation(false);

        $this->expectException(ParticipationTransitionNotAllowed::class);

        $participation->transition($this->machine(), 'expire');
    }

    public function test_the_hold_can_only_be_extended_while_pending(): void
    {
        $pending = $this->participation(true);
        $pending->extendHold(new \DateTimeImmutable('2026-11-10 12:00:00+01:00'));
        self::assertSame('2026-11-10', $pending->holdExpiresAt()?->format('Y-m-d'));
        $pending->extendHold(new \DateTimeImmutable('2026-11-05 12:00:00+01:00'));
        self::assertSame('2026-11-10', $pending->holdExpiresAt()?->format('Y-m-d'), 'never shortened');

        $confirmed = $this->participation(false);
        $confirmed->extendHold(new \DateTimeImmutable('2026-11-10 12:00:00+01:00'));
        self::assertNull($confirmed->holdExpiresAt());
    }

    public function test_the_legal_dimension_changes_without_touching_the_lifecycle(): void
    {
        $participation = $this->participation(true);

        $participation->changeLegalState(LegalState::ReconfirmationRequired, 'new terms');
        $participation->changeLegalState(LegalState::ReconfirmationRequired);

        self::assertSame(LegalState::ReconfirmationRequired, $participation->legalState());
        self::assertSame(ParticipationState::Pending, $participation->state());
        self::assertCount(1, $participation->releaseEvents(), 'the same value twice is one change');
    }
}
