<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * No such capacity bucket (or the event has none yet).
 */
#[AsProblem(code: 'capacity_bucket_not_found', status: 404)]
final class CapacityBucketNotFound extends DomainException
{
    public static function forId(CapacityBucketId $id): self
    {
        return new self(\sprintf('Capacity bucket "%s" was not found.', $id->toString()));
    }
}
