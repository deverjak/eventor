<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\VoidCheckIn;

/**
 * A manager voids an accidental check-in (CHK-07). The reason is mandatory and audited.
 */
final readonly class VoidCheckIn
{
    public function __construct(
        public string $eventId,
        public string $checkInId,
        public string $reason,
        public string $actorAccountId,
    ) {}
}
