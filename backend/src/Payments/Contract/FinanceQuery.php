<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

use Eventor\Payments\Contract\Exception\OrderFinanceNotFound;
use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;

/**
 * Money picture of orders. The ledger is the only truth; this reads the cached fold (`order_finance`) and the
 * mirrored payment requests.
 */
interface FinanceQuery
{
    /**
     * @throws OrderFinanceNotFound
     */
    public function forOrder(OrganizerId $organizer, OrderId $order): OrderFinanceView;

    /**
     * Bulk variant for lists (check-in, admin tables).
     *
     * @param list<OrderId> $orders
     *
     * @return array<string, FinancialStateView> keyed by order id (string); orders without finance are absent
     */
    public function summaries(OrganizerId $organizer, array $orders): array;

    /**
     * Active instructions: one, or two while a supplementary request exists. Empty while the first request is
     * still being created in Pairrot (self-service shows "instructions will arrive by e-mail", PAY-03).
     *
     * @return list<PaymentInstructionsView>
     */
    public function paymentInstructions(OrganizerId $organizer, OrderId $order): array;
}
