<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract\Security;

use Eventor\Identity\Contract\OrganizerView;

/**
 * Voter subject "this permission across the whole organizer" (no event context): a membership restricted to some
 * events is refused, because the action would reach the other events too (ORG-03).
 */
final readonly class OrganizerWidePermissionSubject
{
    public function __construct(public OrganizerView $organizer) {}
}
