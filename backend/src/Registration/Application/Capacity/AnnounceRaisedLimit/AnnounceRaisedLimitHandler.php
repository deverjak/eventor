<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Capacity\AnnounceRaisedLimit;

use Eventor\Registration\Application\Capacity\CapacityReleaser;
use Eventor\Registration\Application\Capacity\Query\CapacityBucketQuery;
use Eventor\Registration\Contract\CapacityReleaseCause;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Registration\Domain\Capacity\BucketCounters;
use Eventor\Registration\Domain\Capacity\ReleasedCapacity;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Works out how many seats the new limit really freed (a bucket that is still overbooked frees none) and announces
 * them with cause `limit_raised`. The counters are not touched, so there is no domain event: the integration event
 * is the whole effect.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Counters are unchanged; the handler only announces seats that Catalog freed by raising a limit (registration.capacity_released).')]
final readonly class AnnounceRaisedLimitHandler
{
    public function __construct(
        private CapacityBucketQuery $buckets,
        private CapacityReleaser $releaser,
    ) {}

    public function __invoke(AnnounceRaisedLimit $command): void
    {
        try {
            $current = $this->buckets->bucket($command->organizerId, $command->bucketId);
        } catch (CapacityBucketNotFound) {
            return; // the bucket is gone; a stale event
        }

        // Evaluate the old limit against today's occupancy: what counts is how much room the change added.
        $before = new BucketCounters($command->previousLimit, $current->held, $current->confirmed);
        $seats = $before->seatsFreedByLimit($command->newLimit);
        if ($seats < 1) {
            return;
        }

        $this->releaser->announce($command->organizerId, new ReleasedCapacity([$command->bucketId], $seats), CapacityReleaseCause::LimitRaised);
    }
}
