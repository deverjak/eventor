<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Organizer;

use Eventor\Shared\Domain\Id\OrganizerId;

interface OrganizerPolicyRepository
{
    public function add(OrganizerPolicy $policy): void;

    /**
     * The policy row of the organizer; created with defaults when an old organizer has none.
     */
    public function getOrCreate(OrganizerId $organizer): OrganizerPolicy;
}
