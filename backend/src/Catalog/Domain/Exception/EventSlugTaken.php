<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The slug of an event is unique within the organizer (PUB-01).
 */
#[AsProblem(code: 'event_slug_taken', status: 409)]
final class EventSlugTaken extends DomainException
{
    public static function create(string $slug): self
    {
        return new self(\sprintf('The address "%s" is already used by another event of this organizer.', $slug));
    }
}
