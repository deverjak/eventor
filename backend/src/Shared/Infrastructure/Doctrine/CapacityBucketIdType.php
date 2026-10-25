<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\CapacityBucketId;

final class CapacityBucketIdType extends AbstractIdType
{
    public const string NAME = 'capacity_bucket_id';

    protected function idClass(): string
    {
        return CapacityBucketId::class;
    }
}
