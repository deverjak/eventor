<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\MaterialId;

final class MaterialIdType extends AbstractIdType
{
    public const string NAME = 'material_id';

    protected function idClass(): string
    {
        return MaterialId::class;
    }
}
