<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a draft event exists. Audited; no integration event (nobody outside Catalog can see a draft).
 */
final readonly class EventCreated implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public string $slug,
        public string $name,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('event', $this->eventId->toString(), 'event.created', $this->organizerId, after: ['slug' => $this->slug, 'name' => $this->name]);
    }
}
