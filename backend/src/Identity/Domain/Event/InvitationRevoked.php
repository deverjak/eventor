<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class InvitationRevoked implements Auditable
{
    public function __construct(
        public InvitationId $invitationId,
        public OrganizerId $organizerId,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'invitation',
            entityId: $this->invitationId->toString(),
            action: 'invitation.revoked',
            organizerId: $this->organizerId,
        );
    }
}
