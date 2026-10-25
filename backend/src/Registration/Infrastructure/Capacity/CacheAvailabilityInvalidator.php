<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Capacity;

use Eventor\Registration\Application\Capacity\AvailabilityInvalidator;
use Eventor\Registration\Application\Capacity\Query\CapacityBucketQuery;
use Eventor\Shared\Domain\Id\EventId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsAlias(AvailabilityInvalidator::class)]
final readonly class CacheAvailabilityInvalidator implements AvailabilityInvalidator
{
    public function __construct(
        #[Autowire(service: 'cache.availability')]
        private TagAwareCacheInterface $cache,
        private CapacityBucketQuery $buckets,
    ) {}

    public function forEvent(EventId $event): void
    {
        $this->cache->invalidateTags([CachedAvailabilityReader::tag($event)]);
    }

    public function forBuckets(array $buckets): void
    {
        $tags = array_map(static fn(EventId $event): string => CachedAvailabilityReader::tag($event), $this->buckets->eventsOf($buckets));
        if ([] !== $tags) {
            $this->cache->invalidateTags($tags);
        }
    }
}
