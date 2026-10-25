<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Eventor\Shared\Domain\Money;

/**
 * A ledger entry compensates an earlier one (S11, PAY-08). `ledgerEntryId` is the new entry, `compensatesId` the corrected one.
 *
 * P0. Planned subscribers: reporting.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.ledger_entry_corrected', version: 1)]
#[PendingSubscribers('reporting')]
final readonly class LedgerEntryCorrected
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public LedgerEntryId $ledgerEntryId,
        public LedgerEntryId $compensatesId,
        public Money $amount,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
