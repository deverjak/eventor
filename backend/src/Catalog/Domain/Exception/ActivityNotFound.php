<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\ActivityId;

/**
 * The activity does not exist, belongs to another event or to another organizer.
 */
#[AsProblem(code: 'activity_not_found', status: 404)]
final class ActivityNotFound extends DomainException
{
    public static function forId(ActivityId $id): self
    {
        return new self(\sprintf('Activity "%s" was not found.', $id->toString()));
    }
}
