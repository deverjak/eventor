<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Request;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Payments\Contract\PaymentRequestPurpose;
use Eventor\Payments\Contract\PaymentRequestStatus;
use Eventor\Payments\Domain\Event\PaymentRequestCancelled;
use Eventor\Payments\Domain\Event\PaymentRequestCreated;
use Eventor\Payments\Domain\Event\PaymentRequestCreationAttemptFailed;
use Eventor\Payments\Domain\Event\PaymentRequestCreationFailed;
use Eventor\Payments\Domain\Event\PaymentRequestMirrored;
use Eventor\Payments\Domain\Event\PaymentRequestRegistered;
use Eventor\Payments\Domain\Event\PaymentRequestRetried;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * Mirror of a payment request in Pairrot (chapter 05/07, ADR 004). Pairrot is the source of truth for the request;
 * Eventor keeps the last known state, the frozen payment instructions and `last_sequence` / `known_paid` for the
 * delta bookkeeping. It never decides the financial state of the order: the ledger does.
 *
 * The amount is immutable (so is Pairrot's); a different amount is a new request with `attempt + 1`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'payment_request', schema: 'payments')]
#[ORM\UniqueConstraint(name: 'payment_request_external_id_key', columns: ['external_id'])]
#[ORM\UniqueConstraint(name: 'payment_request_order_attempt_key', columns: ['order_id', 'attempt'])]
class PaymentRequest implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'string', length: 16, enumType: CreationState::class)]
    private CreationState $creationState = CreationState::Pending;

    #[ORM\Column(type: 'string', length: 16, enumType: PaymentRequestStatus::class)]
    private PaymentRequestStatus $status = PaymentRequestStatus::Requested;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $pairrotId = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $variableSymbol = null;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $accountNumber = null;

    #[ORM\Column(type: 'string', length: 34, nullable: true)]
    private ?string $iban = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $spayd = null;

    #[ORM\Column(type: 'bigint')]
    private int $knownPaidMinor = 0;

    #[ORM\Column(type: 'integer')]
    private int $lastSequence = 0;

    #[ORM\Column(type: 'integer')]
    private int $failedAttempts = 0;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'string', length: 200)]
    private string $externalId;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'payment_request_id')]
        private PaymentRequestId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'order_id')]
        private OrderId $orderId,
        #[ORM\Column(type: 'integer')]
        private int $attempt,
        #[ORM\Column(type: 'string', length: 16, enumType: PaymentRequestPurpose::class)]
        private PaymentRequestPurpose $purpose,
        #[ORM\Embedded(class: Money::class, columnPrefix: 'amount_')]
        private Money $amount,
        #[ORM\Column(type: 'string', length: 60)]
        private string $message,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $dueAt,
        #[ORM\Column(type: 'string', length: 64)]
        private string $subtenantId,
        #[ORM\Column(type: 'string', length: 64)]
        private string $bankConnectionId,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->externalId = self::externalIdFor($orderId, $attempt);
    }

    /** `order_<uuid>_<attempt>`: the idempotency key of the creation in Pairrot; never reused (ADR 004). */
    public static function externalIdFor(OrderId $order, int $attempt): string
    {
        return \sprintf('order_%s_%d', $order->toString(), $attempt);
    }

    public static function register(
        PaymentRequestId $id,
        OrganizerId $organizerId,
        OrderId $orderId,
        int $attempt,
        PaymentRequestPurpose $purpose,
        Money $amount,
        string $message,
        \DateTimeImmutable $dueAt,
        string $subtenantId,
        string $bankConnectionId,
        \DateTimeImmutable $now,
    ): self {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('A payment request asks for a positive amount.');
        }
        if ($attempt < 1) {
            throw new \InvalidArgumentException('Attempts start at 1.');
        }

        $request = new self($id, $organizerId, $orderId, $attempt, $purpose, $amount, $message, $dueAt, $subtenantId, $bankConnectionId, $now);
        $request->record(new PaymentRequestRegistered($organizerId, $orderId, $id, $attempt, $purpose, $amount));

        return $request;
    }

    /**
     * Pairrot answered 201 / 200 replayed. Idempotent: a second confirmation of an issued request changes nothing.
     */
    public function markCreated(
        string $pairrotId,
        string $variableSymbol,
        ?string $accountNumber,
        ?string $iban,
        string $spayd,
        \DateTimeImmutable $now,
    ): void {
        if (CreationState::Created === $this->creationState) {
            return;
        }

        $this->creationState = CreationState::Created;
        $this->status = PaymentRequestStatus::Issued;
        $this->pairrotId = $pairrotId;
        $this->variableSymbol = $variableSymbol;
        $this->accountNumber = $accountNumber;
        $this->iban = $iban;
        $this->spayd = $spayd;
        $this->issuedAt = $now;
        $this->lastError = null;
        $this->record(new PaymentRequestCreated(
            $this->organizerId,
            $this->orderId,
            $this->id,
            $variableSymbol,
            $this->amount,
            $this->dueAt,
            $this->purpose,
            $now,
        ));
    }

    public function recordFailedAttempt(string $error): void
    {
        if (CreationState::Pending !== $this->creationState) {
            return;
        }

        ++$this->failedAttempts;
        $this->lastError = mb_substr($error, 0, 100);
        $this->record(new PaymentRequestCreationAttemptFailed($this->organizerId, $this->id, $this->failedAttempts, $this->lastError));
    }

    /**
     * Retries are exhausted (or Pairrot rejected the request for good): PENDING -> FAILED.
     */
    public function giveUp(string $error, \DateTimeImmutable $now): void
    {
        if (CreationState::Pending !== $this->creationState) {
            return;
        }

        $this->creationState = CreationState::Failed;
        $this->status = PaymentRequestStatus::Failed;
        $this->lastError = mb_substr($error, 0, 100);
        $this->record(new PaymentRequestCreationFailed(
            $this->organizerId,
            $this->orderId,
            $this->id,
            max(1, $this->failedAttempts),
            $this->lastError,
            $now,
        ));
    }

    /**
     * "Try again" from the attention queue: FAILED -> PENDING with a clean counter.
     */
    public function retry(): void
    {
        if (CreationState::Failed !== $this->creationState) {
            throw PaymentRequestException::notFailed($this->id);
        }

        $this->creationState = CreationState::Pending;
        $this->status = PaymentRequestStatus::Requested;
        $this->failedAttempts = 0;
        $this->lastError = null;
        $this->record(new PaymentRequestRetried($this->organizerId, $this->id));
    }

    /**
     * Cancelled by Eventor. The caller has checked that nothing was paid (Pairrot refuses to cancel an allocated request).
     */
    public function cancel(string $reason, \DateTimeImmutable $now): void
    {
        if (null !== $this->cancelledAt) {
            return;
        }

        $this->cancelledAt = $now;
        $this->status = PaymentRequestStatus::Cancelled;
        $this->record(new PaymentRequestCancelled($this->organizerId, $this->orderId, $this->id, $reason));
    }

    /**
     * Mirrors a `payment_request.updated` (or a snapshot). Returns the delta of `paid_amount`, or null when the update
     * is not newer than what is applied (out of order or repeated, chapter 07).
     */
    public function mirror(int $sequence, PaymentRequestStatus $status, int $paidAmountMinor, string $cause, string $eventId, \DateTimeImmutable $occurredAt): ?Money
    {
        if ($sequence <= $this->lastSequence) {
            return null;
        }

        $delta = Money::of($paidAmountMinor - $this->knownPaidMinor, $this->amount->currency);
        $this->lastSequence = $sequence;
        $this->knownPaidMinor = $paidAmountMinor;
        $this->status = $status;
        $this->record(new PaymentRequestMirrored($this->organizerId, $this->orderId, $this->id, $cause, $delta, $eventId, $occurredAt));

        return $delta;
    }

    public function id(): PaymentRequestId
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

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function purpose(): PaymentRequestPurpose
    {
        return $this->purpose;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function dueAt(): \DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function subtenantId(): string
    {
        return $this->subtenantId;
    }

    public function bankConnectionId(): string
    {
        return $this->bankConnectionId;
    }

    public function creationState(): CreationState
    {
        return $this->creationState;
    }

    public function status(): PaymentRequestStatus
    {
        return $this->status;
    }

    public function pairrotId(): ?string
    {
        return $this->pairrotId;
    }

    public function variableSymbol(): ?string
    {
        return $this->variableSymbol;
    }

    public function accountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function iban(): ?string
    {
        return $this->iban;
    }

    public function spayd(): ?string
    {
        return $this->spayd;
    }

    public function knownPaid(): Money
    {
        return Money::of($this->knownPaidMinor, $this->amount->currency);
    }

    public function lastSequence(): int
    {
        return $this->lastSequence;
    }

    public function failedAttempts(): int
    {
        return $this->failedAttempts;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function issuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    /** Not failed and not cancelled: still a way for the participant to pay. */
    public function isActive(): bool
    {
        return CreationState::Failed !== $this->creationState
            && null === $this->cancelledAt
            && PaymentRequestStatus::Cancelled !== $this->status;
    }

    /** What is still expected on this request (zero when covered or overpaid). */
    public function outstanding(): Money
    {
        $outstanding = $this->amount->subtract($this->knownPaid());

        return $outstanding->isPositive() ? $outstanding : Money::zero($this->amount->currency);
    }
}
