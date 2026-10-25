<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Event;

use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * The configured limit of a capacity bucket changed (CAP-06); null = unlimited. `belowOccupancy` = the new limit is below held + confirmed (a warning, nothing is cancelled). Raised by Catalog after `CapacityProvisioner::changeLimit()` succeeded in the same transaction.
 *
 * P0. Planned subscribers: registration, catalog.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'catalog.capacity_changed', version: 1)]
#[PendingSubscribers('catalog')]
final readonly class CapacityChanged
{
    public function __construct(
        public OrganizerId $organizerId,
        public CapacityBucketId $bucketId,
        public CapacityScope $scope,
        public ?int $previousLimit,
        public ?int $newLimit,
        public bool $belowOccupancy,
        public \DateTimeImmutable $occurredAt,
    ) {
        if ((null !== $previousLimit && $previousLimit < 0) || (null !== $newLimit && $newLimit < 0)) {
            throw new \InvalidArgumentException('A capacity limit cannot be negative.');
        }
    }
}
