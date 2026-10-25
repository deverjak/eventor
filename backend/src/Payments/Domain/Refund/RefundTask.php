<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Refund;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Payments\Contract\RefundReason;
use Eventor\Payments\Domain\Event\RefundTaskCancelled;
use Eventor\Payments\Domain\Event\RefundTaskOpened;
use Eventor\Payments\Domain\Event\RefundTaskSettled;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\RefundTaskId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * A manual refund task (PAY-10, PAY-11). Eventor never moves money: a person returns it outside the system and then
 * settles the task, which writes a `refund_settled` ledger entry. State machine `refund_task`: OPEN -> SETTLED | CANCELLED
 * (Symfony Workflow, marking store on `status`).
 */
#[ORM\Entity]
#[ORM\Table(name: 'refund_task', schema: 'payments')]
class RefundTask implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    public const string OPEN = 'OPEN';
    public const string SETTLED = 'SETTLED';
    public const string CANCELLED = 'CANCELLED';

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::OPEN;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $settledAt = null;

    /** @var array{type: string, id: string|null, label: string|null}|null */
    #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    private ?array $settledBy = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $settleMethod = null;

    #[ORM\Column(type: 'string', length: 190, nullable: true)]
    private ?string $settleReference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $settleNote = null;

    #[ORM\Column(type: 'ledger_entry_id', nullable: true)]
    private ?LedgerEntryId $ledgerEntryId = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    /**
     * @param array{type: string, id: string|null, label: string|null} $createdBy
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'refund_task_id')]
        private RefundTaskId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'order_id')]
        private OrderId $orderId,
        #[ORM\Embedded(class: Money::class, columnPrefix: 'amount_')]
        private Money $amount,
        #[ORM\Column(type: 'string', length: 16, enumType: RefundReason::class)]
        private RefundReason $reason,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $note,
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        private array $createdBy,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function open(OrganizerId $organizerId, OrderId $orderId, Money $amount, RefundReason $reason, ?string $note, AuditActor $by, \DateTimeImmutable $now): self
    {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('A refund task is for a positive amount.');
        }

        $task = new self(RefundTaskId::generate(), $organizerId, $orderId, $amount, $reason, $note, self::actorArray($by), $now);
        $task->record(new RefundTaskOpened($organizerId, $task->id, $orderId, $amount, $reason, $now));

        return $task;
    }

    /** Overpayment grew or shrank while the task was open: the amount follows the ledger. */
    public function adjustAmount(Money $amount): void
    {
        if (self::OPEN === $this->status && $amount->isPositive()) {
            $this->amount = $amount;
        }
    }

    public function settle(string $method, ?string $reference, ?string $note, AuditActor $by, LedgerEntryId $entry, \DateTimeImmutable $now): void
    {
        $this->settledAt = $now;
        $this->settledBy = self::actorArray($by);
        $this->settleMethod = $method;
        $this->settleReference = $reference;
        $this->settleNote = $note;
        $this->ledgerEntryId = $entry;
        $this->record(new RefundTaskSettled($this->organizerId, $this->id, $this->orderId, $this->amount, $method, $now));
    }

    public function cancel(string $reason, \DateTimeImmutable $now): void
    {
        $this->cancelledAt = $now;
        $this->record(new RefundTaskCancelled($this->organizerId, $this->id, $this->orderId, $reason));
    }

    // Marking store accessors (Workflow).
    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function id(): RefundTaskId
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

    public function amount(): Money
    {
        return $this->amount;
    }

    public function reason(): RefundReason
    {
        return $this->reason;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function settledAt(): ?\DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function ledgerEntryId(): ?LedgerEntryId
    {
        return $this->ledgerEntryId;
    }

    public function isOpen(): bool
    {
        return self::OPEN === $this->status;
    }

    /**
     * @return array{type: string, id: string|null, label: string|null}
     */
    private static function actorArray(AuditActor $actor): array
    {
        return ['type' => $actor->type->value, 'id' => $actor->id, 'label' => $actor->label];
    }
}
