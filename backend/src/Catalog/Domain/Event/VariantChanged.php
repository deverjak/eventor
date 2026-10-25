<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * Domain event: a variant was added, updated or removed (EVT-06). Internal to Catalog.
 */
final readonly class VariantChanged implements Auditable
{
    /**
     * @param 'added'|'updated'|'removed' $action
     * @param list<string>                $changedFields
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public ActivityId $activityId,
        public VariantId $variantId,
        public string $action,
        public array $changedFields = [],
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('variant', $this->variantId->toString(), 'variant.'.$this->action, $this->organizerId, after: ['activityId' => $this->activityId->toString(), 'changedFields' => $this->changedFields]);
    }
}
