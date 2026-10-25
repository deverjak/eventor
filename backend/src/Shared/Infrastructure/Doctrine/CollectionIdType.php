<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\CollectionId;

final class CollectionIdType extends AbstractIdType
{
    public const string NAME = 'collection_id';

    protected function idClass(): string
    {
        return CollectionId::class;
    }
}
