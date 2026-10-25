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

final readonly class MembershipRevoked implements Auditable
{
    /**
     * @param list<EventId> $eventScope
     */
    public function __construct(
        public MembershipId $membershipId,
        public UserAccountId $userAccountId,
        public OrganizerId $organizerId,
        public Role $role,
        public array $eventScope,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'membership',
            entityId: $this->membershipId->toString(),
            action: 'membership.revoked',
            organizerId: $this->organizerId,
            before: ['userAccountId' => $this->userAccountId->toString(), 'role' => $this->role->value],
        );
    }
}
