<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\PhotoId;

final class PhotoIdType extends AbstractIdType
{
    public const string NAME = 'photo_id';

    protected function idClass(): string
    {
        return PhotoId::class;
    }
}
