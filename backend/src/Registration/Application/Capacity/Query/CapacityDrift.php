<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Capacity\Query;

/**
 * A bucket whose counters disagree with its claims.
 */
final readonly class CapacityDrift
{
    public function __construct(
        public string $bucketId,
        public string $eventId,
        public int $held,
        public int $confirmed,
        public int $claimedHeld,
        public int $claimedConfirmed,
    ) {}
}
