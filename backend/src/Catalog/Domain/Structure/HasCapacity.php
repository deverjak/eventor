<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * The configured limit of an event, activity or variant and the id of the capacity bucket that Registration keeps for
 * it. Catalog decides the id when the entity is created; the bucket row is created when the event is published.
 */
trait HasCapacity
{
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $capacityLimit = null;

    #[ORM\Column(type: 'capacity_bucket_id')]
    private CapacityBucketId $capacityBucketId;

    public function capacityLimit(): ?int
    {
        return $this->capacityLimit;
    }

    public function capacityBucketId(): CapacityBucketId
    {
        return $this->capacityBucketId;
    }
}
