<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\WaveId;

/**
 * A registration wave (EVT-07, P1) with its own capacity bucket. `activityId` null = a wave of the whole event.
 */
final readonly class WaveSnapshot
{
    public function __construct(
        public WaveId $id,
        public ?ActivityId $activityId,
        public string $name,
        public \DateTimeImmutable $opensAt,
        public ?\DateTimeImmutable $closesAt,
        public ?CapacityBucketId $capacityBucketId,
    ) {}
}
