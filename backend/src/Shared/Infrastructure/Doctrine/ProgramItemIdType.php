<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\ProgramItemId;

final class ProgramItemIdType extends AbstractIdType
{
    public const string NAME = 'program_item_id';

    protected function idClass(): string
    {
        return ProgramItemId::class;
    }
}
