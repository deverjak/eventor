<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Eventor\Shared\Domain\Money;

/**
 * The financial state of an order was recomputed after a ledger entry or a change of the amount due. Registration confirms participations on paid/overpaid. `previousState` is null for the first computation.
 *
 * P0. Planned subscribers: registration, communication, reporting.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.order_payment_changed', version: 1)]
#[PendingSubscribers('registration', 'communication', 'reporting')]
final readonly class OrderPaymentChanged
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ?FinancialState $previousState,
        public FinancialState $newState,
        public Money $delta,
        public Money $receivedTotal,
        public Money $amountDue,
        public int $ledgerVersion,
        public \DateTimeImmutable $occurredAt,
    ) {
        if ($ledgerVersion < 1) {
            throw new \InvalidArgumentException('The ledger version starts at 1.');
        }
    }
}
