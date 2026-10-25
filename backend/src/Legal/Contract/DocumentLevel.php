<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

/**
 * Level a legal document is attached to; a lower level may replace a higher one (LEG-02).
 */
enum DocumentLevel: string
{
    case Organizer = 'organizer';
    case Event = 'event';
    case Activity = 'activity';
}
