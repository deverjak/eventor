<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a requirement was created, changed or archived (`action`). Audited.
 */
final readonly class RequirementDefined implements Auditable
{
    /**
     * @param array<string, mixed> $after
     */
    public function __construct(
        public OrganizerId $organizerId,
        public LegalRequirementId $requirementId,
        public string $action,
        public array $after,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('legal_requirement', $this->requirementId->toString(), 'legal.requirement_'.$this->action, $this->organizerId, after: $this->after);
    }
}
