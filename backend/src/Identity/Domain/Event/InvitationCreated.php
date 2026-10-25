<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a team invitation exists. Translated into `identity.member_invited`. The e-mail is deliberately not here.
 */
final readonly class InvitationCreated implements Auditable
{
    public function __construct(
        public InvitationId $invitationId,
        public OrganizerId $organizerId,
        public Role $role,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'invitation',
            entityId: $this->invitationId->toString(),
            action: 'invitation.created',
            organizerId: $this->organizerId,
            after: ['role' => $this->role->value],
        );
    }
}
