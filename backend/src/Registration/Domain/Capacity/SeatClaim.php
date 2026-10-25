<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Capacity;

use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * One participation occupying `seats` in one bucket. A participation produces one SeatClaim per capacity level it
 * touches (event, activity, variant, wave).
 */
final readonly class SeatClaim
{
    public function __construct(
        public ParticipationId $participationId,
        public CapacityBucketId $bucketId,
        public int $seats,
        public ClaimKind $kind,
    ) {
        if ($seats < 1) {
            throw new \InvalidArgumentException('A claim needs at least one seat.');
        }
    }
}
