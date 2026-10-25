<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the Pairrot onboarding stored a subtenant and a bank connection. Translated into the integration
 * event `identity.pairrot_connected`.
 */
final readonly class PairrotConnectedToOrganizer implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public string $subtenantId,
        public string $bankConnectionId,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'organizer',
            entityId: $this->organizerId->toString(),
            action: 'organizer.pairrot_connected',
            organizerId: $this->organizerId,
            after: ['subtenantId' => $this->subtenantId, 'bankConnectionId' => $this->bankConnectionId],
        );
    }
}
