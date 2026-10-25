<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Registration;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * A transition of the registration window (EVT-04) of the event (`activityId` null) or of one activity. With
 * `cascade`, `open` also opens every not yet open activity and `close` closes every activity.
 */
final readonly class ChangeRegistrationState
{
    /**
     * @param string $transition one of open, pause, resume, close (checked by the route)
     */
    public function __construct(
        public EventId $eventId,
        public ?ActivityId $activityId,
        public string $transition,
        public ?string $reason = null,
        public bool $cascade = false,
    ) {}
}
