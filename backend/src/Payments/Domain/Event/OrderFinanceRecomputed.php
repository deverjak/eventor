<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;

/**
 * Domain event: the cached fold of an order was rewritten (new ledger entry, new amount due, refund task opened or
 * closed). Translated into `payments.order_payment_changed` and, for a growing excess, `payments.overpayment_detected`.
 */
final readonly class OrderFinanceRecomputed
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ?FinancialState $previousState,
        public FinancialState $newState,
        public Money $delta,
        public Money $received,
        public Money $amountDue,
        public Money $overpaid,
        public Money $previousOverpaid,
        public int $ledgerVersion,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
