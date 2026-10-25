<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * No organizer with this id (or slug).
 */
#[AsProblem(code: 'organizer_not_found', status: 404)]
final class OrganizerNotFound extends DomainException
{
    public static function forId(OrganizerId $id): self
    {
        return new self(\sprintf('Organizer "%s" was not found.', $id->toString()));
    }

    public static function forSlug(string $slug): self
    {
        return new self(\sprintf('Organizer "%s" was not found.', $slug));
    }
}
