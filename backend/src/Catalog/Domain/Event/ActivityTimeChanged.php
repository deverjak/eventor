<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the start of an activity moved (S12). `important` is the organizer's choice to notify the affected
 * participants; `live` = the event is published, so `catalog.activity_rescheduled` is emitted.
 */
final readonly class ActivityTimeChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public ActivityId $activityId,
        public ?\DateTimeImmutable $previousStartsAt,
        public ?\DateTimeImmutable $newStartsAt,
        public bool $important,
        public bool $live,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            'activity',
            $this->activityId->toString(),
            'activity.rescheduled',
            $this->organizerId,
            ['startsAt' => $this->previousStartsAt?->format(\DATE_ATOM)],
            ['startsAt' => $this->newStartsAt?->format(\DATE_ATOM), 'important' => $this->important],
        );
    }
}
