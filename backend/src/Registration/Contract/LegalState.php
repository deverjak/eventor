<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * `participation.legal_state`: are all confirmations that apply to the participation present and current?
 */
enum LegalState: string
{
    case Complete = 'complete';
    case Incomplete = 'incomplete';
    case ReconfirmationRequired = 'reconfirmation_required';
}
