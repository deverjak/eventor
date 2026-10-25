<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * Who cancelled a participation.
 */
enum CancelledBy: string
{
    case Participant = 'participant';
    case Organizer = 'organizer';
    case System = 'system';
}
