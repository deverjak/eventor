<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\CheckInId;

/**
 * No such check-in for this organizer.
 */
#[AsProblem(code: 'check_in_not_found', status: 404)]
final class CheckInNotFound extends DomainException
{
    public static function forId(CheckInId $id): self
    {
        return new self(\sprintf('Check-in "%s" was not found.', $id->toString()));
    }
}
