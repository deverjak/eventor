<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read model of orders for other modules (Payments needs the number and amount to issue a request, Legal and
 * Communication need the participations of an order).
 */
interface OrderQuery
{
    /**
     * @throws OrderNotFound
     */
    public function get(OrganizerId $organizer, OrderId $order): OrderView;
}
