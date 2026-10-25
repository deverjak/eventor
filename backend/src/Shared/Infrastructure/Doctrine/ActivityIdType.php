<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\ActivityId;

final class ActivityIdType extends AbstractIdType
{
    public const string NAME = 'activity_id';

    protected function idClass(): string
    {
        return ActivityId::class;
    }
}
