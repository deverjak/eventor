<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\CheckInId;

final class CheckInIdType extends AbstractIdType
{
    public const string NAME = 'check_in_id';

    protected function idClass(): string
    {
        return CheckInId::class;
    }
}
