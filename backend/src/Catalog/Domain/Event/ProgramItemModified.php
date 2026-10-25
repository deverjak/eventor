<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ProgramItemId;

/**
 * Domain event: a program item was added, changed or removed (PRG-01..03). `important` offers the notification of the
 * affected participants; `catalog.program_item_changed` is emitted for a published event.
 */
final readonly class ProgramItemModified implements Auditable
{
    /**
     * @param 'added'|'updated'|'removed' $action
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public ProgramItemId $programItemId,
        public ?ActivityId $activityId,
        public string $action,
        public bool $important,
        public bool $live,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('program_item', $this->programItemId->toString(), 'program_item.'.$this->action, $this->organizerId, after: ['eventId' => $this->eventId->toString(), 'important' => $this->important]);
    }
}
