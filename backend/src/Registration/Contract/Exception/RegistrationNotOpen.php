<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * Registration of the event or of a selected activity is not open (not yet, paused or closed).
 */
#[AsProblem(code: 'registration_closed', status: 409)]
final class RegistrationNotOpen extends DomainException
{
    public static function forEvent(EventId $event): self
    {
        return new self(\sprintf('Registration of event "%s" is not open.', $event->toString()));
    }

    public static function forActivity(ActivityId $activity): self
    {
        return new self(\sprintf('Registration of activity "%s" is not open.', $activity->toString()));
    }
}
