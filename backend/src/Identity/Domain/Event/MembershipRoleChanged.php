<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;

final readonly class MembershipRoleChanged implements Auditable
{
    /**
     * @param list<EventId> $previousScope
     * @param list<EventId> $eventScope
     */
    public function __construct(
        public MembershipId $membershipId,
        public UserAccountId $userAccountId,
        public OrganizerId $organizerId,
        public Role $previousRole,
        public Role $role,
        public array $previousScope,
        public array $eventScope,
    ) {}

    public function auditChange(): AuditChange
    {
        $toIds = static fn(EventId $e): string => $e->toString();

        return new AuditChange(
            entityType: 'membership',
            entityId: $this->membershipId->toString(),
            action: 'membership.changed',
            organizerId: $this->organizerId,
            before: ['role' => $this->previousRole->value, 'eventScope' => array_map($toIds, $this->previousScope)],
            after: ['role' => $this->role->value, 'eventScope' => array_map($toIds, $this->eventScope)],
        );
    }
}
