<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Eventor\Shared\Domain\Money;

/**
 * Cash was recorded on site (CHK-08).
 *
 * P0. Planned subscribers: reporting.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.cash_recorded', version: 1)]
#[PendingSubscribers('reporting')]
final readonly class CashRecorded
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public LedgerEntryId $ledgerEntryId,
        public Money $amount,
        public ?CheckInStationId $stationId,
        public \DateTimeImmutable $occurredAt,
    ) {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('Cash is a positive amount; a correction is a separate event.');
        }
    }
}
