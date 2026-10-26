<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Event;

use Eventor\Communication\Contract\SuppressionScope;
use Eventor\Communication\Domain\Suppression\SuppressionId;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: an address was added to or removed from the suppression list. Audited without the address (only its hash is stored).
 */
final readonly class SuppressionChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public SuppressionId $suppressionId,
        public SuppressionScope $scope,
        public string $change,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('suppression', $this->suppressionId->toString(), 'communication.suppression_'.$this->change, $this->organizerId, after: ['scope' => $this->scope->value]);
    }
}
