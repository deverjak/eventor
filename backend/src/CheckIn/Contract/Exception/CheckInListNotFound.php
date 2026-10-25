<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\CheckInListId;

/**
 * No such check-in list.
 */
#[AsProblem(code: 'check_in_list_not_found', status: 404)]
final class CheckInListNotFound extends DomainException
{
    public static function forId(CheckInListId $id): self
    {
        return new self(\sprintf('Check-in list "%s" was not found.', $id->toString()));
    }
}
