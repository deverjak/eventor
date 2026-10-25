<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Capacity;

/**
 * The level a capacity bucket guards (CAP-01). A participation occupies every relevant level at once.
 * Shared because Catalog configures it, Registration stores and counts it, and both publish it in Contract DTOs.
 */
enum CapacityScope: string
{
    case Event = 'event';
    case Activity = 'activity';
    case Variant = 'variant';
    /** P1 */
    case Wave = 'wave';
}
