<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Scheduling;

/**
 * The sweeper found a due delivery that nobody queued yet (a reminder whose time has come).
 */
final readonly class DispatchDelivery
{
    public function __construct(public string $deliveryId) {}
}
