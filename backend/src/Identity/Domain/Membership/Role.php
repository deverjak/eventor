<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Membership;

/**
 * Team roles (specification 3.1). The role is granted per organizer; Event manager and Check-in staff may
 * additionally be restricted to selected events (membership event scope, ORG-03).
 */
enum Role: string
{
    case Owner = 'OWNER';
    case EventManager = 'EVENT_MANAGER';
    case Finance = 'FINANCE';
    case CheckIn = 'CHECK_IN';
}
