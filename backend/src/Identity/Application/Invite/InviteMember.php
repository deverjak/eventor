<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Invite;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;

/**
 * Invites a person by e-mail into the team (ORG-03). A still-pending invitation for the same e-mail is replaced.
 * `role` is a role code (`OWNER`, `EVENT_MANAGER`, `FINANCE`, `CHECK_IN`).
 */
final readonly class InviteMember
{
    /**
     * @param list<EventId> $eventScope
     */
    public function __construct(
        public OrganizerId $organizerId,
        public string $email,
        public string $role,
        public array $eventScope,
        public UserAccountId $invitedBy,
    ) {}
}
