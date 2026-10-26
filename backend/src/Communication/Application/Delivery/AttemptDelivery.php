<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Delivery;

/**
 * One attempt to send a delivery (the worker of `async_mail`).
 */
final readonly class AttemptDelivery
{
    public function __construct(public string $deliveryId) {}
}
