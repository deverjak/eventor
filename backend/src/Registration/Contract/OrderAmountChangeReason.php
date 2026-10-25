<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * Why the amount due of an order changed (`registration.order_amount_changed`).
 */
enum OrderAmountChangeReason: string
{
    case ParticipationCancelled = 'participation_cancelled';
    case VariantChanged = 'variant_changed';
    case EventCancelled = 'event_cancelled';
    case ParticipationTransferred = 'participation_transferred';
    case ManualAdjustment = 'manual_adjustment';
}
