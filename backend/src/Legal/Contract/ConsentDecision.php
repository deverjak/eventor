<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

/**
 * One row of the append-only confirmation ledger (chapter 05, "Právní potvrzení"). Silence is never consent: `requested` is not `granted`.
 */
enum ConsentDecision: string
{
    case Requested = 'requested';
    case Granted = 'granted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
