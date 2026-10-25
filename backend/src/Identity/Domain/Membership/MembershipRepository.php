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

    public function countOwners(OrganizerId $organizer): int;
}
