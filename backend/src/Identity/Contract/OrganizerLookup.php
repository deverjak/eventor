<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Identity\Contract\Exception\OrganizerNotFound;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Organizer lookups that need no signed-in member: the public zone (`/api/public/orgs/{orgSlug}/...`), event
 * subscribers and background jobs. Admin controllers use `OrganizerDirectory` / `#[CurrentOrganizer]` instead.
 */
interface OrganizerLookup
{
    /**
     * Resolves the slug of a public URL. Unknown slugs give null (404 in the public zone).
     */
    public function findBySlug(string $slug): ?OrganizerView;

    /**
     * @throws OrganizerNotFound
     */
    public function get(OrganizerId $id): OrganizerView;
}
