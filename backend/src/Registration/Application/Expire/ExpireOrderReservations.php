<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Expire;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * CAP-04: expires the reservations of one order that ran out. The unit of the sweeper (`ExpireReservations`): one order,
 * one transaction.
 */
final readonly class ExpireOrderReservations
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
    ) {}
}
