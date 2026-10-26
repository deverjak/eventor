<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Payment;

use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Chapter 04, path 4: Registration's reaction to `payments.order_payment_changed`. `positiveDelta` = money came in with
 * this change (a refund or a correction is not a payment for a cancelled participation).
 */
final readonly class ApplyOrderPayment
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public FinancialState $state,
        public bool $positiveDelta,
    ) {}
}
