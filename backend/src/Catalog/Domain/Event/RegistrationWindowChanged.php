<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a transition of the `registration_window` state machine of an event (`activityId` null) or an
 * activity (EVT-04).
 */
final readonly class RegistrationWindowChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public ?ActivityId $activityId,
        public RegistrationState $from,
        public RegistrationState $to,
        public ?string $reason = null,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            null === $this->activityId ? 'event' : 'activity',
            ($this->activityId ?? $this->eventId)->toString(),
            'registration.'.$this->to->value,
            $this->organizerId,
            ['registrationState' => $this->from->value],
            ['registrationState' => $this->to->value],
            $this->reason,
        );
    }
}
