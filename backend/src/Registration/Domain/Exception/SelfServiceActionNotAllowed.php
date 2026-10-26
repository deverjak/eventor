<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The rules of the event do not allow this change through self-service (cancellation switched off or past its
 * deadline, REG-09): the participant has to contact the organizer.
 */
#[AsProblem(code: 'self_service_action_not_allowed', status: 409)]
final class SelfServiceActionNotAllowed extends DomainException
{
    public static function cancellationDisabled(): self
    {
        return new self('The organizer does not allow cancelling through the self-service link.');
    }

    public static function pastDeadline(): self
    {
        return new self('The deadline for cancelling through the self-service link has passed.');
    }

    public static function eventEnded(): self
    {
        return new self('The event is over; changes are no longer possible.');
    }
}
