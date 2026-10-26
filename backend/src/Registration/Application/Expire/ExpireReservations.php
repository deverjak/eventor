<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Expire;

/**
 * The scheduled sweep of reservations that ran out (CAP-04, chapter 06): every minute, all organizers. Idempotent.
 */
final readonly class ExpireReservations
{
    public const int BATCH = 100;
}
