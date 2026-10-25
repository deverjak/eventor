<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration\Capacity;

use Eventor\Catalog\Contract\Event\CapacityChanged;
use Eventor\Registration\Contract\AvailabilityReader;
use Eventor\Registration\Contract\CapacityReleaseCause;
use Eventor\Registration\Contract\Event\CapacityReleased;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Infrastructure\Capacity\CachedAvailabilityReader;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * CachedAvailabilityReader: the public page may be a few seconds old, the write path never is. The cache is
 * invalidated by the subscribers of `catalog.capacity_changed` and `registration.capacity_released`.
 */
final class AvailabilityCacheTest extends CapacityTestCase
{
    private function availableOf(CapacityBucketId $bucket, ?EventId $event = null): ?int
    {
        foreach ($this->service(AvailabilityReader::class)->forEvent($this->organizer, $event ?? $this->event)->buckets as $b) {
            if ($b->bucketId->equals($bucket)) {
                return $b->available;
            }
        }
        self::fail('bucket missing from the availability');
    }

    private function eventBus(): MessageBusInterface
    {
        $bus = static::getContainer()->get('event.bus');
        \assert($bus instanceof MessageBusInterface);

        return $bus;
    }

    private function sentToRegistration(): InMemoryTransport
    {
        $transport = static::getContainer()->get('messenger.transport.registration_events');
        \assert($transport instanceof InMemoryTransport);

        return $transport;
    }

    public function test_the_reader_is_the_cached_decorator(): void
    {
        self::assertInstanceOf(CachedAvailabilityReader::class, $this->service(AvailabilityReader::class));
    }

    public function test_availability_is_served_from_the_cache_until_it_is_invalidated(): void
    {
        $bucket = $this->bucket(3);
        self::assertSame(3, $this->availableOf($bucket));

        $this->claim([$bucket]);

        self::assertSame(3, $this->availableOf($bucket), 'the public read is cached (stale for at most the TTL)');
        self::assertSame(2, $this->service(\Eventor\Registration\Contract\CapacityQuery::class)->bucket($this->organizer, $bucket)->available(), 'the exact view is never cached');
    }

    public function test_the_write_path_ignores_a_stale_cache_and_cannot_oversell(): void
    {
        $bucket = $this->bucket(1);
        self::assertSame(1, $this->availableOf($bucket));
        $this->claim([$bucket]);
        self::assertSame(1, $this->availableOf($bucket), 'the cache still says one seat is free');

        $this->expectException(\Eventor\Registration\Contract\Exception\CapacityExhausted::class);
        $this->service(CapacityAllocator::class)->claim($this->organizer, [
            new \Eventor\Registration\Domain\Capacity\SeatClaim(\Eventor\Shared\Domain\Id\ParticipationId::generate(), $bucket, 1, \Eventor\Registration\Domain\Capacity\ClaimKind::Held),
        ]);
    }

    public function test_a_missing_event_is_not_cached_as_empty(): void
    {
        $unknown = EventId::generate();
        try {
            $this->service(AvailabilityReader::class)->forEvent($this->organizer, $unknown);
            self::fail('No buckets yet.');
        } catch (CapacityBucketNotFound) {
        }

        $bucket = $this->bucket(4, CapacityScope::Event, $unknown);

        self::assertSame(4, $this->availableOf($bucket, $unknown));
    }

    public function test_capacity_released_invalidates_the_cache(): void
    {
        $bucket = $this->bucket(3);
        $participation = $this->claim([$bucket]);
        self::assertSame(2, $this->availableOf($bucket));

        $released = $this->allocator()->release($this->organizer, [$participation]);
        self::assertSame(2, $this->availableOf($bucket), 'stale until the event is handled');

        $this->eventBus()->dispatch(
            new CapacityReleased($this->organizer, $released->bucketIds, $released->seats, CapacityReleaseCause::Cancelled, new \DateTimeImmutable()),
            [TenantStamp::of($this->organizer)],
        );
        $this->drainQueues();

        self::assertSame(3, $this->availableOf($bucket));
    }

    public function test_capacity_changed_invalidates_the_cache_also_when_the_limit_was_lowered(): void
    {
        $bucket = $this->bucket(10);
        self::assertSame(10, $this->availableOf($bucket));
        $change = $this->provisioner()->changeLimit($this->organizer, $bucket, 4, 'smaller');

        $this->eventBus()->dispatch(
            new CapacityChanged($this->organizer, $bucket, CapacityScope::Activity, $change->previousLimit, $change->newLimit, $change->belowOccupancy, new \DateTimeImmutable()),
            [TenantStamp::of($this->organizer)],
        );
        $this->drainQueues();

        self::assertSame(4, $this->availableOf($bucket));
        self::assertSame([], $this->releasedEvents(), 'lowering a limit frees nothing');
    }

    public function test_raising_a_limit_invalidates_and_announces_the_new_room_as_limit_raised(): void
    {
        $bucket = $this->bucket(3);
        $this->claim([$bucket], 2);
        self::assertSame(1, $this->availableOf($bucket));
        $change = $this->provisioner()->changeLimit($this->organizer, $bucket, 8, 'bigger room');

        $this->eventBus()->dispatch(
            new CapacityChanged($this->organizer, $bucket, CapacityScope::Activity, $change->previousLimit, $change->newLimit, $change->belowOccupancy, new \DateTimeImmutable()),
            [TenantStamp::of($this->organizer)],
        );
        $this->drainQueues();

        self::assertSame(6, $this->availableOf($bucket));
        $released = $this->releasedEvents();
        self::assertCount(1, $released);
        self::assertSame(CapacityReleaseCause::LimitRaised, $released[0]->cause);
        self::assertSame(5, $released[0]->seats);
        self::assertTrue($released[0]->bucketIds[0]->equals($bucket));
    }

    public function test_a_raise_that_leaves_the_bucket_overbooked_announces_nothing(): void
    {
        $bucket = $this->bucket(5);
        $this->claim([$bucket], 5);
        $this->provisioner()->changeLimit($this->organizer, $bucket, 2, 'shrunk');
        $this->sentToRegistration()->reset();
        $change = $this->provisioner()->changeLimit($this->organizer, $bucket, 4, 'a little back');

        $this->eventBus()->dispatch(
            new CapacityChanged($this->organizer, $bucket, CapacityScope::Activity, $change->previousLimit, $change->newLimit, $change->belowOccupancy, new \DateTimeImmutable()),
            [TenantStamp::of($this->organizer)],
        );
        $this->drainQueues();

        self::assertSame([], $this->releasedEvents());
    }

    /**
     * @return list<CapacityReleased>
     */
    private function releasedEvents(): array
    {
        $events = [];
        foreach ($this->sentToRegistration()->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof CapacityReleased) {
                $events[] = $message;
            }
        }

        return $events;
    }
}
