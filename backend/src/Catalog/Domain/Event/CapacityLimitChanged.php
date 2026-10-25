<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the configured capacity limit of an event, activity or variant changed (CAP-06). `live` = the bucket
 * exists in Registration, so `catalog.capacity_changed` is emitted.
 */
final readonly class CapacityLimitChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public CapacityScope $scope,
        public string $scopeId,
        public CapacityBucketId $bucketId,
        public ?int $previousLimit,
        public ?int $newLimit,
        public bool $belowOccupancy,
        public bool $live,
        public ?string $reason = null,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            $this->scope->value,
            $this->scopeId,
            'capacity.limit_changed',
            $this->organizerId,
            ['limit' => $this->previousLimit],
            ['limit' => $this->newLimit, 'belowOccupancy' => $this->belowOccupancy],
            $this->reason,
        );
    }
}
