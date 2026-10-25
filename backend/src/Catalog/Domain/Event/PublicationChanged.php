<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Catalog\Contract\PublicationState;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a transition of the `event_publication` state machine happened (EVT-03). The publisher turns the
 * target state into `catalog.event_published` / `event_cancelled` / `event_completed`.
 */
final readonly class PublicationChanged implements Auditable
{
    /**
     * @param list<ActivityId> $activityIds activities of the event
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public PublicationState $from,
        public PublicationState $to,
        public string $slug,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public array $activityIds,
        public ?string $reason = null,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            'event',
            $this->eventId->toString(),
            'event.'.$this->to->value,
            $this->organizerId,
            ['publicationState' => $this->from->value],
            ['publicationState' => $this->to->value],
            $this->reason,
        );
    }
}
