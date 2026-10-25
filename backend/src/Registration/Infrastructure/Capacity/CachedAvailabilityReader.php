<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Capacity;

use Eventor\Registration\Contract\AvailabilityReader;
use Eventor\Registration\Contract\BucketAvailability;
use Eventor\Registration\Contract\EventAvailability;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Public availability under a registration rush (NFR performance): a short-lived, tag-aware cache in front of
 * `DbalAvailabilityReader`. The entry carries the tag `availability.event.<id>`; the subscribers of
 * `catalog.capacity_changed` and `registration.capacity_released` invalidate it, the TTL is only the safety net.
 *
 * Only this decorator touches the cache, and only on the read path: the allocator and the provisioner do not know
 * about it, so a stale entry can make a page say "3 left" for a few seconds but can never oversell a seat.
 */
#[AsDecorator(decorates: AvailabilityReader::class)]
final readonly class CachedAvailabilityReader implements AvailabilityReader
{
    public const int TTL_SECONDS = 30;

    public function __construct(
        #[AutowireDecorated]
        private AvailabilityReader $inner,
        #[Autowire(service: 'cache.availability')]
        private TagAwareCacheInterface $cache,
    ) {}

    public static function tag(EventId $event): string
    {
        return 'availability.event.'.$event->toString();
    }

    public function forEvent(OrganizerId $organizer, EventId $event): EventAvailability
    {
        /** @var list<array{bucketId: string, scope: string, scopeId: string, available: int|null}> $buckets */
        $buckets = $this->cache->get(
            \sprintf('availability.%s.%s', $organizer->toString(), $event->toString()),
            function (ItemInterface $item) use ($organizer, $event): array {
                $item->tag(self::tag($event))->expiresAfter(self::TTL_SECONDS);

                // Plain arrays in the cache, not objects: an entry outlives a deploy that changes the DTOs.
                return array_map(static fn(BucketAvailability $bucket): array => [
                    'bucketId' => $bucket->bucketId->toString(),
                    'scope' => $bucket->scope->value,
                    'scopeId' => $bucket->scopeId,
                    'available' => $bucket->available,
                ], $this->inner->forEvent($organizer, $event)->buckets);
            },
        );

        return new EventAvailability($event, array_map(static fn(array $bucket): BucketAvailability => new BucketAvailability(
            CapacityBucketId::fromString($bucket['bucketId']),
            CapacityScope::from($bucket['scope']),
            $bucket['scopeId'],
            $bucket['available'],
        ), $buckets));
    }
}
