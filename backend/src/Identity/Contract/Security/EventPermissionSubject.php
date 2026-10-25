<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract\Security;

use Eventor\Identity\Contract\OrganizerView;
use Eventor\Shared\Domain\Id\EventId;

/**
 * Voter subject "this permission for this event of the organizer": a membership restricted to some events
 * (`membership_event_scope`) is granted only for those events (ORG-03).
 */
final readonly class EventPermissionSubject
{
    public function __construct(
        public OrganizerView $organizer,
        public EventId $event,
    ) {}
}
