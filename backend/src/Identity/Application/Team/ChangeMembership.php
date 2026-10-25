<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Team;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Changes the role (code: `OWNER`, `EVENT_MANAGER`, `FINANCE`, `CHECK_IN`) and the optional event scope of a team
 * member (ORG-03). An empty scope means all events.
 */
final readonly class ChangeMembership
{
    /**
     * @param list<EventId> $eventScope
     */
    public function __construct(
        public OrganizerId $organizerId,
        public MembershipId $membershipId,
        public string $role,
        public array $eventScope,
    ) {}
}
