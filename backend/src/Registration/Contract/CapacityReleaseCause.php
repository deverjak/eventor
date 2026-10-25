<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * Why seats became free (`registration.capacity_released`).
 */
enum CapacityReleaseCause: string
{
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case LimitRaised = 'limit_raised';
    case OfferExpired = 'offer_expired';
}
