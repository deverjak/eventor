<?php

declare(strict_types=1);

namespace Eventor\Content\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\CollectionId;

/**
 * No such collection, or it is not visible to this audience.
 */
#[AsProblem(code: 'collection_not_found', status: 404)]
final class CollectionNotFound extends DomainException
{
    public static function forId(CollectionId $id): self
    {
        return new self(\sprintf('Collection "%s" was not found.', $id->toString()));
    }
}
