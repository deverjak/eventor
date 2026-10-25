<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\AddOnId;

final class AddOnIdType extends AbstractIdType
{
    public const string NAME = 'add_on_id';

    protected function idClass(): string
    {
        return AddOnId::class;
    }
}
