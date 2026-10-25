<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Capacity;

/**
 * Life of a `capacity_claim` row: held -> confirmed, either -> released (cancel, expiry), released -> held|confirmed
 * again only through `CapacityAllocator::reclaim()`.
 */
enum ClaimStatus: string
{
    case Held = 'held';
    case Confirmed = 'confirmed';
    case Released = 'released';

    public function isActive(): bool
    {
        return self::Released !== $this;
    }
}
