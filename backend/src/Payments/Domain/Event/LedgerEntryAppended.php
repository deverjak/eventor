<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Payments\Domain\Ledger\LedgerEntryKind;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;

/**
 * Domain event: a line was appended to the ledger. Audited (who booked money, with which reason); the application
 * layer translates it into `payments.cash_recorded` / `payments.ledger_entry_corrected`.
 */
final readonly class LedgerEntryAppended implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public LedgerEntryId $entryId,
        public LedgerEntryKind $kind,
        public Money $amount,
        public ?LedgerEntryId $compensatesId,
        public ?CheckInStationId $stationId,
        public ?string $reason,
        public \DateTimeImmutable $occurredAt,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'ledger_entry',
            entityId: $this->entryId->toString(),
            action: 'ledger.'.$this->kind->value,
            organizerId: $this->organizerId,
            after: [
                'orderId' => $this->orderId->toString(),
                'amountMinor' => $this->amount->minor,
                'currency' => $this->amount->currency->value,
                'compensatesId' => $this->compensatesId?->toString(),
            ],
            reason: $this->reason,
        );
    }
}
