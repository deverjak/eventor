<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Order;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Hands out the human-readable order numbers `EV-2026-000123`: a gap-free counter per organizer and year. Runs in the
 * transaction of `PlaceOrder`, so a rolled-back order does not burn a number.
 */
interface OrderNumbers
{
    public function next(OrganizerId $organizer, \DateTimeImmutable $now): string;
}
