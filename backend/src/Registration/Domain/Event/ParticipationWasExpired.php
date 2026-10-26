<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: the reservation of a participation expired (CAP-04) and its seats went back. Published as
 * `registration.participation_expired`.
 */
final readonly class ParticipationWasExpired
{
    /**
     * @param list<CapacityBucketId> $bucketIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $participationId,
        public array $bucketIds,
    ) {}
}
