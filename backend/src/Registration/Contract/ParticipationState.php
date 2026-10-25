<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * Lifecycle of a participation (chapter 05). Legal completeness and answers are separate dimensions (`LegalState`, `AnswersState`); check-in is derived by CheckIn.
 */
enum ParticipationState: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case RequiresAttention = 'requires_attention';
    case Cancelled = 'cancelled';
    case Transferred = 'transferred';
}
