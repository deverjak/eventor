<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Ledger;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * One immutable line of the payment ledger (ADR 001). The application role may only INSERT and SELECT it
 * (REVOKE in the migration, verified by SchemaGrantsTest); a mistake is fixed by a compensating entry, never
 * by an update. The ledger carries no personal data: the actor is an account id or a system name.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'ledger_entry', schema: 'payments')]
class LedgerEntry implements TenantScoped
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'ledger_entry_id')]
        private LedgerEntryId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'order_id')]
        private OrderId $orderId,
        #[ORM\Column(type: 'string', length: 32, enumType: LedgerEntryKind::class)]
        private LedgerEntryKind $kind,
        #[ORM\Embedded(class: Money::class, columnPrefix: 'amount_')]
        private Money $amount,
        #[ORM\Column(type: 'string', length: 16, enumType: LedgerSource::class)]
        private LedgerSource $source,
        #[ORM\Column(type: 'string', length: 190, nullable: true)]
        private ?string $sourceRef,
        #[ORM\Column(type: 'ledger_entry_id', nullable: true)]
        private ?LedgerEntryId $compensatesId,
        #[ORM\Column(type: 'payment_request_id', nullable: true)]
        private ?PaymentRequestId $paymentRequestId,
        #[ORM\Column(type: 'check_in_station_id', nullable: true)]
        private ?CheckInStationId $stationId,
        /** @var array{type: string, id: string|null, label: string|null} */
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        private array $actor,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $reason,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $occurredAt,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $recordedAt,
    ) {
        if ($amount->isZero()) {
            throw new \InvalidArgumentException('A ledger entry never has a zero amount.');
        }
    }

    /**
     * The change of the paid amount of a payment request in Pairrot (delta of `paid_amount`, chapter 07).
     * `externalRef` is the id of the Pairrot event: it makes the booking idempotent.
     */
    public static function pairrotDelta(
        OrganizerId $organizerId,
        OrderId $orderId,
        PaymentRequestId $paymentRequestId,
        Money $delta,
        string $externalRef,
        ?LedgerEntryId $compensates,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $now,
    ): self {
        $kind = $delta->isPositive() ? LedgerEntryKind::PairrotAllocation : LedgerEntryKind::PairrotRevocation;

        return new self(
            LedgerEntryId::generate(),
            $organizerId,
            $orderId,
            $kind,
            $delta,
            LedgerSource::Pairrot,
            $externalRef,
            $delta->isNegative() ? $compensates : null,
            $paymentRequestId,
            null,
            self::actorArray(AuditActor::pairrot()),
            null,
            $occurredAt,
            $now,
        );
    }

    public static function cash(
        OrganizerId $organizerId,
        OrderId $orderId,
        Money $amount,
        ?CheckInStationId $station,
        string $idempotencyKey,
        AuditActor $actor,
        ?string $note,
        \DateTimeImmutable $now,
    ): self {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('Cash is a positive amount; a mistake is fixed by a correction.');
        }

        return new self(
            LedgerEntryId::generate(),
            $organizerId,
            $orderId,
            LedgerEntryKind::Cash,
            $amount,
            LedgerSource::Cash,
            $idempotencyKey,
            null,
            null,
            $station,
            self::actorArray($actor),
            $note,
            $now,
            $now,
        );
    }

    /**
     * Compensates `$original` with its negated amount (S11). The reason is mandatory.
     */
    public static function cashCorrection(self $original, string $idempotencyKey, AuditActor $actor, string $reason, \DateTimeImmutable $now): self
    {
        if (LedgerEntryKind::Cash !== $original->kind) {
            throw new \InvalidArgumentException('Only a cash entry can be corrected as cash.');
        }

        return new self(
            LedgerEntryId::generate(),
            $original->organizerId,
            $original->orderId,
            LedgerEntryKind::CashCorrection,
            $original->amount->negate(),
            LedgerSource::Cash,
            $idempotencyKey,
            $original->id,
            null,
            $original->stationId,
            self::actorArray($actor),
            $reason,
            $now,
            $now,
        );
    }

    /**
     * Finance books a payment that Pairrot does not know (a transfer to another account, a voucher) or removes one
     * (negative amount). The reason is mandatory.
     */
    public static function manualCorrection(
        OrganizerId $organizerId,
        OrderId $orderId,
        Money $amount,
        string $idempotencyKey,
        AuditActor $actor,
        string $reason,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            LedgerEntryId::generate(),
            $organizerId,
            $orderId,
            LedgerEntryKind::ManualCorrection,
            $amount,
            LedgerSource::Manual,
            $idempotencyKey,
            null,
            null,
            null,
            self::actorArray($actor),
            $reason,
            $now,
            $now,
        );
    }

    /**
     * Closing a refund task: the money left the organizer (negative amount). One entry per task.
     */
    public static function refundSettled(
        OrganizerId $organizerId,
        OrderId $orderId,
        Money $refunded,
        string $refundTaskId,
        AuditActor $actor,
        ?string $note,
        \DateTimeImmutable $now,
    ): self {
        if (!$refunded->isPositive()) {
            throw new \InvalidArgumentException('Pass the refunded amount as a positive number; the entry stores it negated.');
        }

        return new self(
            LedgerEntryId::generate(),
            $organizerId,
            $orderId,
            LedgerEntryKind::RefundSettled,
            $refunded->negate(),
            LedgerSource::Manual,
            'refund:'.$refundTaskId,
            null,
            null,
            null,
            self::actorArray($actor),
            $note,
            $now,
            $now,
        );
    }

    public function id(): LedgerEntryId
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

    public function kind(): LedgerEntryKind
    {
        return $this->kind;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function source(): LedgerSource
    {
        return $this->source;
    }

    public function sourceRef(): ?string
    {
        return $this->sourceRef;
    }

    public function compensatesId(): ?LedgerEntryId
    {
        return $this->compensatesId;
    }

    public function paymentRequestId(): ?PaymentRequestId
    {
        return $this->paymentRequestId;
    }

    public function stationId(): ?CheckInStationId
    {
        return $this->stationId;
    }

    /**
     * @return array{type: string, id: string|null, label: string|null}
     */
    public function actor(): array
    {
        return $this->actor;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function recordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    /**
     * @return array{type: string, id: string|null, label: string|null}
     */
    private static function actorArray(AuditActor $actor): array
    {
        return ['type' => $actor->type->value, 'id' => $actor->id, 'label' => $actor->label];
    }
}
