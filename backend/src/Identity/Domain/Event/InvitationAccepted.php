<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;

final readonly class InvitationAccepted implements Auditable
{
    public function __construct(
        public InvitationId $invitationId,
        public OrganizerId $organizerId,
        public UserAccountId $acceptedBy,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'invitation',
            entityId: $this->invitationId->toString(),
            action: 'invitation.accepted',
            organizerId: $this->organizerId,
            after: ['acceptedBy' => $this->acceptedBy->toString()],
        );
    }
}
