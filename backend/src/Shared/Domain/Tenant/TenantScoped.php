<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Tenant;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Marker for entities that belong to one organizer. The entity must map a field named `organizerId`
 * to the column `organizer_id`; the Doctrine `organizer` filter adds `organizer_id = :current` to every
 * query on it (ADR 005).
 */
interface TenantScoped
{
    public function organizerId(): OrganizerId;
}
