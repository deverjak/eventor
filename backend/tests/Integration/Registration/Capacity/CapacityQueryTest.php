<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration\Capacity;

use Eventor\Registration\Contract\AvailabilityReader;
use Eventor\Registration\Contract\CapacityQuery;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

final class CapacityQueryTest extends CapacityTestCase
{
    private function query(): CapacityQuery
    {
        return $this->service(CapacityQuery::class);
    }

    public function test_it_reports_exact_limit_held_confirmed_and_available(): void
    {
        $bucket = $this->bucket(10, CapacityScope::Variant);
        $this->claim([$bucket], 3);
        $this->claim([$bucket], 2, ClaimKind::Confirmed);

        $occupancy = $this->query()->bucket($this->organizer, $bucket);

        self::assertSame(10, $occupancy->limit);
        self::assertSame(3, $occupancy->held);
        self::assertSame(2, $occupancy->confirmed);
        self::assertSame(5, $occupancy->available());
        self::assertFalse($occupancy->overbooked);
        self::assertSame(CapacityScope::Variant, $occupancy->scope);
    }

    public function test_an_unlimited_bucket_has_no_available_number(): void
    {
        $bucket = $this->bucket(null);

        self::assertNull($this->query()->bucket($this->organizer, $bucket)->available());
    }

    public function test_an_overbooked_bucket_reports_it_and_zero_available(): void
    {
        $bucket = $this->bucket(5);
        $this->claim([$bucket], 4);
        $this->provisioner()->changeLimit($this->organizer, $bucket, 2, 'smaller');

        $occupancy = $this->query()->bucket($this->organizer, $bucket);

        self::assertTrue($occupancy->overbooked);
        self::assertSame(0, $occupancy->available());
    }

    public function test_for_event_returns_every_level_in_a_stable_order_and_only_that_event(): void
    {
        $variant = $this->bucket(3, CapacityScope::Variant);
        $event = $this->bucket(100, CapacityScope::Event);
        $activity = $this->bucket(10, CapacityScope::Activity);
        $this->bucket(7, CapacityScope::Event, EventId::generate());

        $buckets = $this->query()->forEvent($this->organizer, $this->event);

        self::assertSame(
            [$event->toString(), $activity->toString(), $variant->toString()],
            array_map(static fn($b): string => $b->bucketId->toString(), $buckets),
        );
    }

    public function test_it_never_shows_another_organizers_buckets(): void
    {
        $bucket = $this->bucket(5);

        self::assertSame([], $this->query()->forEvent(OrganizerId::generate(), $this->event));
        $this->expectException(CapacityBucketNotFound::class);
        $this->query()->bucket(OrganizerId::generate(), $bucket);
    }

    public function test_an_unknown_bucket_is_not_found(): void
    {
        $this->expectException(CapacityBucketNotFound::class);
        $this->query()->bucket($this->organizer, CapacityBucketId::generate());
    }

    public function test_the_availability_reader_reports_what_is_available_per_bucket(): void
    {
        $activity = $this->bucket(4, CapacityScope::Activity);
        $unlimited = $this->bucket(null, CapacityScope::Event);
        $this->claim([$activity], 1);

        $availability = $this->service(AvailabilityReader::class)->forEvent($this->organizer, $this->event);

        $byBucket = [];
        foreach ($availability->buckets as $bucket) {
            $byBucket[$bucket->bucketId->toString()] = $bucket->available;
        }
        self::assertSame([$unlimited->toString() => null, $activity->toString() => 3], $byBucket);
    }

    public function test_the_availability_of_an_event_without_buckets_is_not_found(): void
    {
        $this->expectException(CapacityBucketNotFound::class);
        $this->service(AvailabilityReader::class)->forEvent($this->organizer, EventId::generate());
    }
}
