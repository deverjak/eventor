<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract;

/**
 * The exception matrix of the check-in desk (CHK-09) as far as it ends in an escalation.
 */
enum CheckInSituation: string
{
    case AlreadyCheckedIn = 'already_checked_in';
    case Unpaid = 'unpaid';
    case MissingConfirmation = 'missing_confirmation';
    case RequiresAttention = 'requires_attention';
    case CancelledOrExpired = 'cancelled_or_expired';
    case WrongActivity = 'wrong_activity';
}
