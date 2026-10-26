<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * Why a participation was cancelled, as a closed code for `registration.participation_cancelled`. The free text of the
 * cancellation (it can contain personal data) stays in Registration and in the audit; events never carry it.
 */
enum CancellationReason: string
{
    case ParticipantRequest = 'participant_request';
    case Organizer = 'organizer';
    case EventCancelled = 'event_cancelled';
    case Expired = 'expired';
    case Other = 'other';
}
