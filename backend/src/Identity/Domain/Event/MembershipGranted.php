<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;

/**
 * Domain event: a member was added to an organizer. Audited here; the application layer translates it into the
 * public integration event `MembershipChanged`.
 */
final readonly class MembershipGranted implements Auditable
{
    public function __construct(
        public MembershipId $membershipId,
        public UserAccountId $userAccountId,
        public OrganizerId $organizerId,
        public Role $role,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'membership',
            entityId: $this->membershipId->toString(),
            action: 'membership.granted',
            organizerId: $this->organizerId,
            after: ['userAccountId' => $this->userAccountId->toString(), 'role' => $this->role->value],
        );
    }
}
