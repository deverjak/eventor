<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Participation;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Domain\Event\ParticipationChanged;
use Eventor\Registration\Domain\Workflow\Transitions;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Id\WaveId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Tenant\TenantScoped;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * One person in one activity and variant: the only bearer of capacity (chapter 03). Four independent dimensions
 * (chapter 05): the lifecycle `state` (the `participation` state machine), `legalState`, `answersState` and, owned by
 * CheckIn, the check-in. The reservation is not a state but `holdExpiresAt` (only while `pending`) plus the held
 * counter of the buckets. The state setter is the marking store of the workflow: use the transition methods.
 */
#[ORM\Entity]
#[ORM\Table(name: 'participation', schema: 'registration')]
class Participation implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    private bool $seatsTaken = false;

    #[ORM\Column(type: 'string', length: 20, enumType: ParticipationState::class)]
    private ParticipationState $state;

    #[ORM\Column(type: 'string', length: 30, enumType: LegalState::class)]
    private LegalState $legalState = LegalState::Complete;

    #[ORM\Column(type: 'string', length: 12, enumType: AnswersState::class)]
    private AnswersState $answersState = AnswersState::Complete;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $holdExpiresAt;

    #[ORM\Column(type: 'integer')]
    private int $qrVersion = 1;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column(type: 'string', length: 12, nullable: true, enumType: CancelledBy::class)]
    private ?CancelledBy $cancelledBy = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'participation_id')]
        private ParticipationId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'order_id')]
        private OrderId $orderId,
        #[ORM\Column(type: 'person_id')]
        private PersonId $personId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        #[ORM\Column(type: 'activity_id')]
        private ActivityId $activityId,
        #[ORM\Column(type: 'variant_id')]
        private VariantId $variantId,
        #[ORM\Column(type: 'wave_id', nullable: true)]
        private ?WaveId $waveId,
        ParticipationState $state,
        ?\DateTimeImmutable $holdExpiresAt,
        #[ORM\Column(type: 'string', length: 16)]
        private string $registrationCode,
        #[ORM\Column(type: 'string', length: 32, nullable: true)]
        private ?string $categoryCode,
        #[ORM\Embedded(class: Money::class, columnPrefix: 'price_')]
        private Money $price,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->state = $state;
        $this->holdExpiresAt = $holdExpiresAt;
    }

    /**
     * A participation is born `pending` with a reservation (bank transfer) or `confirmed` (cash, free): the two
     * starting points of the state machine (chapter 05). The order records the placement; nothing is recorded here.
     */
    public static function create(
        ParticipationId $id,
        OrganizerId $organizerId,
        OrderId $orderId,
        PersonId $personId,
        EventId $eventId,
        ActivityId $activityId,
        VariantId $variantId,
        ?WaveId $waveId,
        ?\DateTimeImmutable $holdExpiresAt,
        string $registrationCode,
        ?string $categoryCode,
        Money $price,
        \DateTimeImmutable $now,
    ): self {
        $state = null === $holdExpiresAt ? ParticipationState::Confirmed : ParticipationState::Pending;

        return new self($id, $organizerId, $orderId, $personId, $eventId, $activityId, $variantId, $waveId, $state, $holdExpiresAt, $registrationCode, $categoryCode, $price, $now);
    }

    public function id(): ParticipationId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function activityId(): ActivityId
    {
        return $this->activityId;
    }

    public function variantId(): VariantId
    {
        return $this->variantId;
    }

    public function waveId(): ?WaveId
    {
        return $this->waveId;
    }

    public function state(): ParticipationState
    {
        return $this->state;
    }

    public function legalState(): LegalState
    {
        return $this->legalState;
    }

    public function answersState(): AnswersState
    {
        return $this->answersState;
    }

    public function holdExpiresAt(): ?\DateTimeImmutable
    {
        return $this->holdExpiresAt;
    }

    public function registrationCode(): string
    {
        return $this->registrationCode;
    }

    public function qrVersion(): int
    {
        return $this->qrVersion;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function cancelledBy(): ?CancelledBy
    {
        return $this->cancelledBy;
    }

    /**
     * Set by the automatic restore, which takes the seats before the transition: the `restore` guard must not look for
     * room that the participation already occupies. Not persisted, valid for this unit of work only.
     */
    public function seatsAlreadyTaken(): void
    {
        $this->seatsTaken = true;
    }

    public function hasTakenSeats(): bool
    {
        return $this->seatsTaken;
    }

    /** Pending, confirmed or flagged: the participation counts for the order (an amount is due for it). */
    public function isActive(): bool
    {
        return \in_array($this->state, [ParticipationState::Pending, ParticipationState::Confirmed, ParticipationState::RequiresAttention], true);
    }

    /**
     * Applies a transition of the `participation` state machine and records the audit entry. The transition names are
     * those of chapter 05: confirm, confirm_on_site, expire, restore, flag_late_payment, cancel, transfer.
     *
     * @param array<string, mixed> $context information for the guards
     */
    public function transition(WorkflowInterface $machine, string $transition, ?string $reason = null, array $context = []): void
    {
        $from = $this->state;
        Transitions::apply($machine, $this, $transition, $context);
        $this->record(new ParticipationChanged($this->organizerId, $this->orderId, $this->id, 'state', $from->value, $this->state->value, $transition, $reason));
    }

    /**
     * Cancels (`cancel`), kills the QR code (`qr_version++`, CHK-03) and remembers who and why (REG-09, REG-14).
     *
     * @param array<string, mixed> $context
     */
    public function cancel(WorkflowInterface $machine, CancelledBy $by, string $reason, \DateTimeImmutable $now, array $context = []): void
    {
        $this->transition($machine, 'cancel', $reason, $context);
        $this->cancelledAt = $now;
        $this->cancelReason = mb_substr($reason, 0, 500);
        $this->cancelledBy = $by;
        ++$this->qrVersion;
    }

    /** The reservation was prolonged (payment instructions arrived late, chapter 04 path 3). */
    public function extendHold(\DateTimeImmutable $until): void
    {
        if (ParticipationState::Pending !== $this->state || null === $this->holdExpiresAt || $until <= $this->holdExpiresAt) {
            return;
        }
        $before = $this->holdExpiresAt;
        $this->holdExpiresAt = $until;
        $this->record(new ParticipationChanged($this->organizerId, $this->orderId, $this->id, 'hold_expires_at', $before->format(\DATE_ATOM), $until->format(\DATE_ATOM), 'extend_hold', null));
    }

    public function changeLegalState(LegalState $to, ?string $reason = null): void
    {
        if ($to === $this->legalState) {
            return;
        }
        $from = $this->legalState;
        $this->legalState = $to;
        $this->record(new ParticipationChanged($this->organizerId, $this->orderId, $this->id, 'legal_state', $from->value, $to->value, 'legal_state', $reason));
    }

    public function changeAnswersState(AnswersState $to, ?string $reason = null): void
    {
        if ($to === $this->answersState) {
            return;
        }
        $from = $this->answersState;
        $this->answersState = $to;
        $this->record(new ParticipationChanged($this->organizerId, $this->orderId, $this->id, 'answers_state', $from->value, $to->value, 'answers_state', $reason));
    }

    /** Marking store of the `participation` state machine. @internal */
    public function getState(): ParticipationState
    {
        return $this->state;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @internal marking store of the `participation` state machine; use transition()
     */
    public function setState(ParticipationState $state, array $context = []): void
    {
        $this->state = $state;
        if (ParticipationState::Pending !== $state) {
            $this->holdExpiresAt = null; // the reservation lives only while pending (CHECK participation_hold_iff_pending)
        }
    }
}
