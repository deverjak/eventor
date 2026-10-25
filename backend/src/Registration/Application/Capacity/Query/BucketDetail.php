<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Capacity\Query;

use Eventor\Registration\Contract\BucketOccupancy;
use Eventor\Registration\Domain\Capacity\BucketCounters;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * One bucket as the admin sees it (CAP-03): the Contract counters plus the unit and the owning event.
 */
final readonly class BucketDetail implements \JsonSerializable
{
    public function __construct(
        public CapacityBucketId $bucketId,
        public EventId $eventId,
        public CapacityScope $scope,
        public string $scopeId,
        public CapacityUnit $unit,
        public ?int $limit,
        public int $held,
        public int $confirmed,
    ) {}

    public function counters(): BucketCounters
    {
        return new BucketCounters($this->limit, $this->held, $this->confirmed);
    }

    public function occupancy(): BucketOccupancy
    {
        return new BucketOccupancy($this->bucketId, $this->scope, $this->scopeId, $this->limit, $this->held, $this->confirmed, $this->counters()->isOverbooked());
    }

    /**
     * @return array{bucketId: string, scope: string, scopeId: string, unit: string, limit: int|null, held: int, confirmed: int, available: int|null, overbooked: bool}
     */
    public function jsonSerialize(): array
    {
        $counters = $this->counters();

        return [
            'bucketId' => $this->bucketId->toString(),
            'scope' => $this->scope->value,
            'scopeId' => $this->scopeId,
            'unit' => $this->unit->value,
            'limit' => $this->limit,
            'held' => $this->held,
            'confirmed' => $this->confirmed,
            'available' => $counters->free(),
            'overbooked' => $counters->isOverbooked(),
        ];
    }
}
