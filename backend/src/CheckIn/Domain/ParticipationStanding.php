<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain;

/**
 * The lifecycle state of a participation as far as the desk cares (Registration's `ParticipationState` mapped by the
 * application layer, so the domain does not depend on another module).
 */
enum ParticipationStanding: string
{
    /** Confirmed: a seat is taken for good. */
    case Confirmed = 'confirmed';
    /** Held, waiting for the payment. */
    case Pending = 'pending';
    case RequiresAttention = 'requires_attention';
    /** Cancelled, expired or transferred: the QR code is dead. */
    case Inactive = 'inactive';
}
