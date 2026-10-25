<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

interface OrganizerDirectory
{
    /**
     * The organizer with this slug, but only when the user is one of its members. A missing organizer and a
     * foreign organizer are indistinguishable by design (404 either way, ADR 005).
     *
     * @param string $userIdentifier the Symfony user identifier of the signed-in member
     */
    public function findForMember(string $slug, string $userIdentifier): ?OrganizerView;
}
