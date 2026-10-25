<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * No such participation for this organizer.
 */
#[AsProblem(code: 'participation_not_found', status: 404)]
final class ParticipationNotFound extends DomainException
{
    public static function forId(ParticipationId $id): self
    {
        return new self(\sprintf('Participation "%s" was not found.', $id->toString()));
    }
}
