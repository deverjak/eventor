<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Role x permission decisions for other modules' voters. The matrix and organizer policy exceptions stay inside Identity.
 */
interface AccessPolicy
{
    public function isAllowed(Permission $permission, OrganizerId $organizer, string $userIdentifier): bool;

    /**
     * The role of the user in the organizer (`OWNER`, `EVENT_MANAGER`, `FINANCE`, `CHECK_IN`), null for non-members.
     */
    public function roleOf(OrganizerId $organizer, string $userIdentifier): ?string;
}
