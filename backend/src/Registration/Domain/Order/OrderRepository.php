<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Order;

use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Shared\Domain\Id\OrderId;

/**
 * Orders of the active tenant (the Doctrine `organizer` filter scopes every lookup).
 */
interface OrderRepository
{
    /**
     * @throws OrderNotFound
     */
    public function get(OrderId $id): Order;

    public function add(Order $order): void;
}
