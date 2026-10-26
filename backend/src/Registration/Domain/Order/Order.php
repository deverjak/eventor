<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Order;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Registration\Contract\AttentionCause;
use Eventor\Registration\Contract\CancellationReason;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Registration\Contract\ConfirmationReason;
use Eventor\Registration\Contract\OrderAmountChangeReason;
use Eventor\Registration\Contract\RestoredBy;
use Eventor\Registration\Domain\Event\AnswersWereFoundIncomplete;
use Eventor\Registration\Domain\Event\DataWasCorrected;
use Eventor\Registration\Domain\Event\LatePaymentWasReceived;
use Eventor\Registration\Domain\Event\OrderAmountDueChanged;
use Eventor\Registration\Domain\Event\OrderWasPlaced;
use Eventor\Registration\Domain\Event\ParticipationNeedsAttention;
use Eventor\Registration\Domain\Event\ParticipationsWereConfirmed;
use Eventor\Registration\Domain\Event\ParticipationWasCancelled;
use Eventor\Registration\Domain\Event\ParticipationWasExpired;
use Eventor\Registration\Domain\Event\ParticipationWasRestored;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * The root of the registration aggregate (chapter 03): the contact, the method, the current amount due and the
 * snapshot of the form version the answers were validated against. People, participations and answer sets refer to it
 * by id and are loaded through their own repositories; every integration-bearing fact of the order is recorded here
 * (one place, one per-order batch), the per-participation audit comes from `ParticipationChanged`.
 *
 * `amountDue` is the amount after cancellations and changes; the history is in the audit and in
 * `registration.order_amount_changed`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'orders', schema: 'registration')]
#[ORM\UniqueConstraint(name: 'orders_number_key', columns: ['organizer_id', 'number'])]
class Order implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'order_id')]
        private OrderId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        #[ORM\Column(type: 'string', length: 32)]
        private string $number,
        #[ORM\Embedded(class: Orderer::class, columnPrefix: 'orderer_')]
        private Orderer $orderer,
        #[ORM\Column(type: 'string', length: 16, enumType: PaymentMethod::class)]
        private PaymentMethod $paymentMethod,
        #[ORM\Embedded(class: Money::class, columnPrefix: 'amount_due_')]
        private Money $amountDue,
        #[ORM\Column(type: 'string', length: 12, enumType: OrderOrigin::class)]
        private OrderOrigin $origin,
        #[ORM\Column(type: 'form_version_id')]
        private FormVersionId $formVersionId,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $placedAt,
    ) {}

    /**
     * @param list<ParticipationId> $participationIds
     */
    public static function place(
        OrderId $id,
        OrganizerId $organizerId,
        EventId $eventId,
        string $number,
        Orderer $orderer,
        PaymentMethod $paymentMethod,
        Money $amountDue,
        OrderOrigin $origin,
        FormVersionId $formVersionId,
        array $participationIds,
        ?\DateTimeImmutable $holdExpiresAt,
        \DateTimeImmutable $now,
    ): self {
        $order = new self($id, $organizerId, $eventId, $number, $orderer, $paymentMethod, $amountDue, $origin, $formVersionId, $now);
        $order->record(new OrderWasPlaced($organizerId, $id, $number, $eventId, $paymentMethod, $amountDue, $participationIds, $holdExpiresAt));

        return $order;
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function number(): string
    {
        return $this->number;
    }

    public function orderer(): Orderer
    {
        return $this->orderer;
    }

    public function paymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function amountDue(): Money
    {
        return $this->amountDue;
    }

    public function formVersionId(): FormVersionId
    {
        return $this->formVersionId;
    }

    public function placedAt(): \DateTimeImmutable
    {
        return $this->placedAt;
    }

    /**
     * Sets the amount due (never below zero) and records the change for Payments; nothing happens when it is the same.
     */
    public function changeAmountDue(Money $new, OrderAmountChangeReason $reason): void
    {
        if ($new->isNegative()) {
            $new = Money::zero($new->currency);
        }
        if ($new->minor === $this->amountDue->minor) {
            return;
        }
        $previous = $this->amountDue;
        $this->amountDue = $new;
        $this->record(new OrderAmountDueChanged($this->organizerId, $this->id, $previous, $new, $reason));
    }

    /** REG-09: the orderer fixed the contact (a typo in the e-mail). */
    public function correctOrderer(Orderer $orderer): void
    {
        $this->orderer = $orderer;
    }

    /**
     * Audit trail of a data correction (field keys only, never values).
     *
     * @param list<string> $fields
     */
    public function noteDataCorrected(string $subjectType, string $subjectId, array $fields): void
    {
        $this->record(new DataWasCorrected($this->organizerId, $this->id, $subjectType, $subjectId, $fields));
    }

    /**
     * @param list<ParticipationId> $participationIds
     */
    public function noteConfirmed(array $participationIds, ConfirmationReason $reason): void
    {
        if ([] !== $participationIds) {
            $this->record(new ParticipationsWereConfirmed($this->organizerId, $this->id, $participationIds, $reason));
        }
    }

    public function noteCancelled(ParticipationId $participation, CancelledBy $by, CancellationReason $reason): void
    {
        $this->record(new ParticipationWasCancelled($this->organizerId, $this->id, $participation, $by, $reason));
    }

    /**
     * @param list<CapacityBucketId> $bucketIds
     */
    public function noteExpired(ParticipationId $participation, array $bucketIds): void
    {
        $this->record(new ParticipationWasExpired($this->organizerId, $this->id, $participation, $bucketIds));
    }

    public function noteNeedsAttention(ParticipationId $participation, AttentionCause $cause): void
    {
        $this->record(new ParticipationNeedsAttention($this->organizerId, $this->id, $participation, $cause));
    }

    public function noteRestored(ParticipationId $participation, RestoredBy $by): void
    {
        $this->record(new ParticipationWasRestored($this->organizerId, $this->id, $participation, $by));
    }

    /**
     * @param list<ParticipationId> $participationIds
     */
    public function noteLatePayment(array $participationIds, bool $capacityAvailable, bool $autoRestore): void
    {
        $this->record(new LatePaymentWasReceived($this->organizerId, $this->id, $participationIds, $capacityAvailable, $autoRestore));
    }

    /**
     * @param list<ParticipationId> $participationIds
     */
    public function noteAnswersIncomplete(array $participationIds, FormVersionId $currentVersion): void
    {
        $this->record(new AnswersWereFoundIncomplete($this->organizerId, $this->id, $participationIds, $currentVersion));
    }
}
