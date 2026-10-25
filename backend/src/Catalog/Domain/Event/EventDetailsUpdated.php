<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the descriptive data of an event changed. `live` = the event is no longer a draft, so other modules
 * are told (`catalog.event_details_changed`).
 */
final readonly class EventDetailsUpdated implements Auditable
{
    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public array $changedFields,
        public bool $live,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('event', $this->eventId->toString(), 'event.details_changed', $this->organizerId, after: ['changedFields' => $this->changedFields]);
    }
}
