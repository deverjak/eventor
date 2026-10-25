<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

use Eventor\Payments\Contract\Exception\CashNotAllowed;
use Eventor\Payments\Contract\Exception\OrderFinanceNotFound;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;

/**
 * Cash taken on site (CHK-08, PAY-07). A writing facade: it appends a `cash` ledger entry and recomputes the
 * financial state in the transaction of the caller (`RecordCashAndCheckIn` in CheckIn), so the operator never sees
 * "paid but not checked in". Registration confirms the participations afterwards through `payments.order_payment_changed`.
 */
interface CashDesk
{
    /**
     * Idempotent per `idempotencyKey` (a repeated request returns the first receipt).
     *
     * @throws OrderFinanceNotFound
     * @throws CashNotAllowed when the organizer policy does not let the actor take cash
     */
    public function recordCash(OrganizerId $organizer, OrderId $order, Money $amount, ?CheckInStationId $station, string $idempotencyKey, ?string $note = null): CashReceipt;
}
