<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\CheckInListId;

/**
 * The event was cancelled, so the list does not admit anybody any more.
 */
#[AsProblem(code: 'check_in_list_closed', status: 409)]
final class CheckInListClosed extends DomainException
{
    public static function forId(CheckInListId $id): self
    {
        return new self(\sprintf('Check-in list "%s" is closed.', $id->toString()));
    }
}
