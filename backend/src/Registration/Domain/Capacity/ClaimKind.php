<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Capacity;

/**
 * Which counter a new claim lands in. Bank-transfer participations hold the seat until the TTL expires; cash and
 * free participations are confirmed at once (ADR 002).
 */
enum ClaimKind: string
{
    case Held = 'held';
    case Confirmed = 'confirmed';

    public function status(): ClaimStatus
    {
        return match ($this) {
            self::Held => ClaimStatus::Held,
            self::Confirmed => ClaimStatus::Confirmed,
        };
    }
}
