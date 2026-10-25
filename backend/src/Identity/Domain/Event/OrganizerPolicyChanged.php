<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class OrganizerPolicyChanged implements Auditable
{
    /**
     * @param array<string, scalar> $before
     * @param array<string, scalar> $after
     */
    public function __construct(
        public OrganizerId $organizerId,
        public array $before,
        public array $after,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'organizer_policy',
            entityId: $this->organizerId->toString(),
            action: 'organizer.policy_changed',
            organizerId: $this->organizerId,
            before: $this->before,
            after: $this->after,
        );
    }
}
