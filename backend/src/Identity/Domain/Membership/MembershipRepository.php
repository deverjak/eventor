<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Membership;

use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;

interface MembershipRepository
{
    public function add(Membership $membership): void;

    /**
     * Needs an active tenant (Membership is tenant-scoped); the id is matched inside the organizer.
     */
    public function find(MembershipId $id, OrganizerId $organizer): ?Membership;

    public function remove(Membership $membership): void;

    public function exists(UserAccountId $account, OrganizerId $organizer): bool;

    /**
     * Locks the owner memberships of the organizer (until the end of the transaction) and counts them, so two
     * concurrent demotions or removals cannot both see "another owner is left".
     */
    public function lockAndCountOwners(OrganizerId $organizer): int;
}
