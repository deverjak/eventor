<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * Creates the finance row of a new order. A writing facade: it runs in the `PlaceOrder` transaction (chapter 04, path 2,
 * step 8), so the order never exists without a financial state and self-service can show it at once. The payment
 * request itself is created later by the subscriber of `registration.order_placed`.
 *
 * Chapter 02 does not list this call among the shared transactions; chapter 04 does. The book's flow wins.
 */
interface OrderFinanceInitializer
{
    /**
     * `unpaid` for transfer and cash, `not_required` for a free order. Idempotent per order.
     */
    public function initialize(OrganizerId $organizer, OrderId $order, PaymentMethod $method, Money $amountDue): FinancialStateView;
}
