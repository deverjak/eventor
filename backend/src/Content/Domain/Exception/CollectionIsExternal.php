<?php

declare(strict_types=1);

namespace Eventor\Content\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\CollectionId;

/**
 * Photos are uploaded only into an internal gallery; an external collection is just a link (POST-02).
 */
#[AsProblem(code: 'collection_is_external', status: 409)]
final class CollectionIsExternal extends DomainException
{
    public static function forId(CollectionId $id): self
    {
        return new self(\sprintf('Collection "%s" links to an external gallery and takes no photos.', $id->toString()));
    }
}
