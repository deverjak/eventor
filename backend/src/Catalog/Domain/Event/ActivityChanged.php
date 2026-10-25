<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: an activity was added, updated or removed (EVT-05). Internal to Catalog.
 */
final readonly class ActivityChanged implements Auditable
{
    /**
     * @param 'added'|'updated'|'removed' $action
     * @param list<string>                $changedFields
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public ActivityId $activityId,
        public string $action,
        public array $changedFields = [],
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('activity', $this->activityId->toString(), 'activity.'.$this->action, $this->organizerId, after: ['eventId' => $this->eventId->toString(), 'changedFields' => $this->changedFields]);
    }
}
