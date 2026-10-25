<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Unmatched;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Payments\Contract\MatchingResult;
use Eventor\Payments\Domain\Event\AllocationRequested;
use Eventor\Payments\Domain\Event\UnmatchedTransactionClosed;
use Eventor\Payments\Domain\Event\UnmatchedTransactionRecorded;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * Mirror of a bank transaction Pairrot could not allocate (PAY-06): the reconciliation queue. It closes when Pairrot
 * reports the transaction allocated or ignored. Ledger entries never come from here: a manual allocation only asks
 * Pairrot, and the money enters through the inbox like any other payment.
 */
#[ORM\Entity]
#[ORM\Table(name: 'unmatched_transaction', schema: 'payments')]
class UnmatchedTransaction implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    public const string ALLOCATED = 'allocated';
    public const string IGNORED = 'ignored';
    public const string CLOSED = 'closed';

    #[ORM\Column(type: 'string', length: 16)]
    private string $handlingState;

    #[ORM\Column(type: 'integer')]
    private int $lastSequence;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    private ?string $resolution = null;

    #[ORM\Column(type: 'order_id', nullable: true)]
    private ?OrderId $resolvedOrderId = null;

    #[ORM\Column(type: 'payment_request_id', nullable: true)]
    private ?PaymentRequestId $resolvedPaymentRequestId = null;

    /** @var array{type: string, id: string|null, label: string|null}|null */
    #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    private ?array $allocationRequestedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $allocationReason = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'bank_transaction_id')]
        private BankTransactionId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'string', length: 64)]
        private string $pairrotTxId,
        #[ORM\Embedded(class: Money::class, columnPrefix: 'amount_')]
        private Money $amount,
        #[ORM\Column(type: 'string', length: 10, nullable: true)]
        private ?string $variableSymbol,
        #[ORM\Column(type: 'date_immutable')]
        private \DateTimeImmutable $bookedOn,
        #[ORM\Column(type: 'string', length: 32, enumType: MatchingResult::class)]
        private MatchingResult $matchingResult,
        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private ?string $suggestedRequestPairrotId,
        string $handlingState,
        int $lastSequence,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->handlingState = $handlingState;
        $this->lastSequence = $lastSequence;
    }

    public static function receive(
        OrganizerId $organizerId,
        string $pairrotTxId,
        Money $amount,
        ?string $variableSymbol,
        \DateTimeImmutable $bookedOn,
        MatchingResult $result,
        ?string $suggestedRequestPairrotId,
        string $handlingState,
        int $sequence,
        \DateTimeImmutable $now,
    ): self {
        $transaction = new self(BankTransactionId::generate(), $organizerId, $pairrotTxId, $amount, $variableSymbol, $bookedOn, $result, $suggestedRequestPairrotId, $handlingState, $sequence, $now);
        $transaction->record(new UnmatchedTransactionRecorded($organizerId, $transaction->id, $amount, $variableSymbol, $result, $bookedOn, $now));

        return $transaction;
    }

    /**
     * A newer state of the transaction arrived and it is open: remember the latest matching explanation.
     */
    public function refresh(int $sequence, MatchingResult $result, ?string $suggestedRequestPairrotId, string $handlingState): bool
    {
        if ($sequence <= $this->lastSequence) {
            return false;
        }

        $this->lastSequence = $sequence;
        $this->matchingResult = $result;
        $this->suggestedRequestPairrotId = $suggestedRequestPairrotId;
        $this->handlingState = $handlingState;

        return true;
    }

    /**
     * Pairrot allocated or ignored the transaction (or it matched after all): the queue item closes.
     */
    public function close(int $sequence, string $resolution, ?OrderId $order, ?PaymentRequestId $paymentRequest, \DateTimeImmutable $now): bool
    {
        if ($sequence <= $this->lastSequence || null !== $this->resolvedAt) {
            return false;
        }

        $this->lastSequence = $sequence;
        $this->handlingState = $resolution;
        $this->resolvedAt = $now;
        $this->resolution = $resolution;
        $this->resolvedOrderId = $order ?? $this->resolvedOrderId;
        $this->resolvedPaymentRequestId = $paymentRequest ?? $this->resolvedPaymentRequestId;
        $this->record(new UnmatchedTransactionClosed($this->organizerId, $this->id, $resolution, $this->resolvedPaymentRequestId, $this->resolvedOrderId, $now));

        return true;
    }

    /**
     * Finance asked Pairrot to allocate the transaction (the reason is mandatory and audited).
     */
    public function allocationRequested(OrderId $order, PaymentRequestId $paymentRequest, string $reason, AuditActor $by): void
    {
        $this->resolvedOrderId = $order;
        $this->resolvedPaymentRequestId = $paymentRequest;
        $this->allocationReason = $reason;
        $this->allocationRequestedBy = ['type' => $by->type->value, 'id' => $by->id, 'label' => $by->label];
        $this->record(new AllocationRequested($this->organizerId, $this->id, $order, $paymentRequest, $reason));
    }

    public function id(): BankTransactionId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function pairrotTxId(): string
    {
        return $this->pairrotTxId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function variableSymbol(): ?string
    {
        return $this->variableSymbol;
    }

    public function bookedOn(): \DateTimeImmutable
    {
        return $this->bookedOn;
    }

    public function matchingResult(): MatchingResult
    {
        return $this->matchingResult;
    }

    public function suggestedRequestPairrotId(): ?string
    {
        return $this->suggestedRequestPairrotId;
    }

    public function handlingState(): string
    {
        return $this->handlingState;
    }

    public function isOpen(): bool
    {
        return null === $this->resolvedAt;
    }

    public function resolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function resolution(): ?string
    {
        return $this->resolution;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
