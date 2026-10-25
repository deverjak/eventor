<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * No such person for this organizer.
 */
#[AsProblem(code: 'person_not_found', status: 404)]
final class PersonNotFound extends DomainException
{
    public static function forId(PersonId $id): self
    {
        return new self(\sprintf('Person "%s" was not found.', $id->toString()));
    }
}
