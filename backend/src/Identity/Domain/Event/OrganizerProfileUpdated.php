<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the organizer profile changed. The audit entry names the changed fields only (contact data is personal).
 */
final readonly class OrganizerProfileUpdated implements Auditable
{
    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        public OrganizerId $organizerId,
        public array $changedFields,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'organizer',
            entityId: $this->organizerId->toString(),
            action: 'organizer.profile_updated',
            organizerId: $this->organizerId,
            after: ['changedFields' => $this->changedFields],
        );
    }
}
