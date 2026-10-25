<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The organizer of the current admin request, as other modules see it (never the entity).
 * Injected into controllers with `#[CurrentOrganizer] OrganizerView $organizer`.
 */
final readonly class OrganizerView
{
    public function __construct(
        public OrganizerId $id,
        public string $slug,
        public string $name,
    ) {}
}
