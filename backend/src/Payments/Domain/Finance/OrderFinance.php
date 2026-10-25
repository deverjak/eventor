<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Finance;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Payments\Domain\Event\AmountDueChanged;
use Eventor\Payments\Domain\Event\LedgerEntryAppended;
use Eventor\Payments\Domain\Event\OrderFinanceRecomputed;
use Eventor\Payments\Domain\Ledger\FoldResult;
use Eventor\Payments\Domain\Ledger\LedgerEntry;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * The cached fold of the ledger of one order (chapter 03/05). It is only ever rewritten from a `FoldResult`, in the
 * transaction of the ledger entry or the change of the amount due that caused it, so it can be rebuilt from the
 * ledger at any time (the nightly check does exactly that). `ledgerVersion` rises with every rewrite.
 */
#[ORM\Entity]
#[ORM\Table(name: 'order_finance', schema: 'payments')]
class OrderFinance implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'string', length: 16, enumType: FinancialState::class)]
    private FinancialState $state;

    #[ORM\Column(type: 'bigint')]
    private int $dueMinor;

    #[ORM\Column(type: 'bigint')]
    private int $receivedMinor = 0;

    #[ORM\Column(type: 'bigint')]
    private int $receivedTransferMinor = 0;

    #[ORM\Column(type: 'bigint')]
    private int $receivedCashMinor = 0;

    #[ORM\Column(type: 'bigint')]
    private int $refundedMinor = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $disputed = false;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $dueChangeReason = null;

    #[ORM\Column(type: 'integer')]
    private int $ledgerVersion = 1;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'order_id')]
        private OrderId $orderId,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'string', length: 16, enumType: PaymentMethod::class)]
        private PaymentMethod $paymentMethod,
        #[ORM\Column(type: 'string', length: 3, enumType: Currency::class)]
        private Currency $currency,
        Money $amountDue,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->dueMinor = $amountDue->minor;
        $this->updatedAt = $createdAt;
        $this->state = PaymentMethod::Free === $paymentMethod || $amountDue->isZero() ? FinancialState::NotRequired : FinancialState::Unpaid;
    }

    public static function initialize(OrganizerId $organizerId, OrderId $orderId, PaymentMethod $method, Money $amountDue, \DateTimeImmutable $now): self
    {
        if ($amountDue->isNegative()) {
            throw new \InvalidArgumentException('The amount due cannot be negative.');
        }

        return new self($orderId, $organizerId, $method, $amountDue->currency, $amountDue, $now);
    }

    /**
     * Rewrites the cache from a fold computed by the caller. `$delta` is the net change of the money held (sum of the
     * entries that caused the rewrite). Records `OrderFinanceRecomputed`.
     */
    public function applyFold(FoldResult $fold, Money $delta, \DateTimeImmutable $now): void
    {
        $previousState = $this->state;
        $previousOverpaid = $this->overpaid();

        $this->state = $fold->state;
        $this->dueMinor = $fold->amountDue->minor;
        $this->receivedMinor = $fold->received->minor;
        $this->receivedTransferMinor = $fold->receivedTransfer->minor;
        $this->receivedCashMinor = $fold->receivedCash->minor;
        $this->refundedMinor = $fold->refunded->minor;
        ++$this->ledgerVersion;
        $this->updatedAt = $now;

        $this->record(new OrderFinanceRecomputed(
            $this->organizerId,
            $this->orderId,
            $previousState,
            $this->state,
            $delta,
            $fold->received,
            $fold->amountDue,
            $fold->overpaid(),
            $previousOverpaid,
            $this->ledgerVersion,
            $now,
        ));
    }

    /**
     * Registration changed the amount due (cancellation, variant change, ...). `$fold` was computed against `$newDue`.
     */
    public function changeAmountDue(Money $newDue, ?string $reason, FoldResult $fold, \DateTimeImmutable $now): void
    {
        $previous = $this->amountDue();
        $this->dueChangeReason = $reason;
        $this->record(new AmountDueChanged($this->organizerId, $this->orderId, $previous, $newDue, $reason));
        $this->applyFold($fold, Money::zero($this->currency), $now);
    }

    public function entryAppended(LedgerEntry $entry, \DateTimeImmutable $now): void
    {
        $this->record(new LedgerEntryAppended(
            $this->organizerId,
            $this->orderId,
            $entry->id(),
            $entry->kind(),
            $entry->amount(),
            $entry->compensatesId(),
            $entry->stationId(),
            $entry->reason(),
            $now,
        ));
    }

    public function markDisputed(bool $disputed): void
    {
        $this->disputed = $disputed;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function paymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function state(): FinancialState
    {
        return $this->state;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function amountDue(): Money
    {
        return Money::of($this->dueMinor, $this->currency);
    }

    public function received(): Money
    {
        return Money::of($this->receivedMinor, $this->currency);
    }

    public function receivedTransfer(): Money
    {
        return Money::of($this->receivedTransferMinor, $this->currency);
    }

    public function receivedCash(): Money
    {
        return Money::of($this->receivedCashMinor, $this->currency);
    }

    public function refunded(): Money
    {
        return Money::of($this->refundedMinor, $this->currency);
    }

    /**
     * Money that can still be returned: everything received minus what was already refunded, never below zero.
     */
    public function refundable(): Money
    {
        $left = $this->received()->subtract($this->refunded());

        return $left->isPositive() ? $left : Money::zero($this->currency);
    }

    public function overpaid(): Money
    {
        $excess = $this->received()->subtract($this->amountDue());

        return $excess->isPositive() ? $excess : Money::zero($this->currency);
    }

    public function isDisputed(): bool
    {
        return $this->disputed;
    }

    public function dueChangeReason(): ?string
    {
        return $this->dueChangeReason;
    }

    public function ledgerVersion(): int
    {
        return $this->ledgerVersion;
    }

    public function view(): FinancialStateView
    {
        return new FinancialStateView($this->state, $this->amountDue(), $this->received());
    }
}
