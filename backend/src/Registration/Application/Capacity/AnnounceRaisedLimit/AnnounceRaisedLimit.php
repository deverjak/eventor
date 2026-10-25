<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Capacity\AnnounceRaisedLimit;

use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Catalog raised (or removed) the limit of a bucket: tell the waitlist that seats may be free now.
 */
final readonly class AnnounceRaisedLimit
{
    public function __construct(
        public OrganizerId $organizerId,
        public CapacityBucketId $bucketId,
        public ?int $previousLimit,
        public ?int $newLimit,
    ) {}
}
