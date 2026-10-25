<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event (internal to Identity): a new tenant exists. Audited; no integration event in P0.
 */
final readonly class OrganizerRegistered implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public string $slug,
        public string $name,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'organizer',
            entityId: $this->organizerId->toString(),
            action: 'organizer.registered',
            organizerId: $this->organizerId,
            after: ['slug' => $this->slug, 'name' => $this->name],
        );
    }
}
