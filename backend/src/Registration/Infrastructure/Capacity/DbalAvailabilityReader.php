<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Capacity;

use Eventor\Registration\Application\Capacity\Query\CapacityBucketQuery;
use Eventor\Registration\Contract\AvailabilityReader;
use Eventor\Registration\Contract\BucketAvailability;
use Eventor\Registration\Contract\EventAvailability;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Availability read from the buckets (PUB-03), never by counting participations. The `AvailabilityReader` alias is
 * decorated by `CachedAvailabilityReader`.
 */
#[AsAlias(AvailabilityReader::class)]
final readonly class DbalAvailabilityReader implements AvailabilityReader
{
    public function __construct(private CapacityBucketQuery $buckets) {}

    public function forEvent(OrganizerId $organizer, EventId $event): EventAvailability
    {
        $occupancies = $this->buckets->forEvent($organizer, $event);
        if ([] === $occupancies) {
            throw new CapacityBucketNotFound(\sprintf('Event "%s" has no capacity buckets.', $event->toString()));
        }

        return new EventAvailability($event, array_map(static fn($bucket): BucketAvailability => new BucketAvailability(
            $bucket->bucketId,
            $bucket->scope,
            $bucket->scopeId,
            $bucket->available(),
        ), $occupancies));
    }
}
