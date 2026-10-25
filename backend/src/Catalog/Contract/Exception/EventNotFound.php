<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\EventId;

/**
 * The event does not exist, is not published (public zone) or belongs to another organizer.
 */
#[AsProblem(code: 'event_not_found', status: 404)]
final class EventNotFound extends DomainException
{
    public static function forId(EventId $id): self
    {
        return new self(\sprintf('Event "%s" was not found.', $id->toString()));
    }

    public static function forSlug(string $slug): self
    {
        return new self(\sprintf('Event "%s" was not found.', $slug));
    }
}
