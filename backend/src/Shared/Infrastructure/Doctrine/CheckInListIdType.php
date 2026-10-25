<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\CheckInListId;

final class CheckInListIdType extends AbstractIdType
{
    public const string NAME = 'check_in_list_id';

    protected function idClass(): string
    {
        return CheckInListId::class;
    }
}
