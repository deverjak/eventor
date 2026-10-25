<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration\Capacity;

use Eventor\Registration\Contract\CapacityBucketSpec;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

final class CapacityProvisionerTest extends CapacityTestCase
{
    public function test_it_creates_empty_buckets_for_every_level_of_the_event(): void
    {
        $eventBucket = CapacityBucketId::generate();
        $activityBucket = CapacityBucketId::generate();
        $variantBucket = CapacityBucketId::generate();
        $activityId = EventId::generate()->toString();

        $this->provisioner()->provision($this->organizer, $this->event, [
            new CapacityBucketSpec($eventBucket, CapacityScope::Event, $this->event->toString(), 200, CapacityUnit::Seat),
            new CapacityBucketSpec($activityBucket, CapacityScope::Activity, $activityId, null, CapacityUnit::Seat),
            new CapacityBucketSpec($variantBucket, CapacityScope::Variant, EventId::generate()->toString(), 12, CapacityUnit::Team),
        ]);

        self::assertSame(3, $this->dbInt('SELECT count(*) FROM registration.capacity_bucket WHERE event_id = ? AND organizer_id = ?', [$this->event->toString(), $this->organizer->toString()]));
        self::assertSame(['limit' => 200, 'held' => 0, 'confirmed' => 0, 'overbooked_allowed' => false], $this->counters($eventBucket));
        self::assertSame(['limit' => null, 'held' => 0, 'confirmed' => 0, 'overbooked_allowed' => false], $this->counters($activityBucket));
        self::assertSame('team', $this->dbString('SELECT unit FROM registration.capacity_bucket WHERE id = ?', [$variantBucket->toString()]));
    }

    public function test_provisioning_is_idempotent_per_bucket_id_and_never_resets_counters(): void
    {
        $bucket = CapacityBucketId::generate();
        $spec = new CapacityBucketSpec($bucket, CapacityScope::Activity, EventId::generate()->toString(), 5, CapacityUnit::Seat);
        $this->provisioner()->provision($this->organizer, $this->event, [$spec]);
        $this->claim([$bucket], 2);

        $this->provisioner()->provision($this->organizer, $this->event, [$spec]);

        self::assertSame(1, $this->dbInt('SELECT count(*) FROM registration.capacity_bucket WHERE id = ?', [$bucket->toString()]));
        self::assertSame(2, $this->counters($bucket)['held']);
    }

    public function test_a_negative_limit_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->provisioner()->provision($this->organizer, $this->event, [
            new CapacityBucketSpec(CapacityBucketId::generate(), CapacityScope::Event, $this->event->toString(), -1, CapacityUnit::Seat),
        ]);
    }

    public function test_raising_the_limit_reports_the_change(): void
    {
        $bucket = $this->bucket(5);
        $this->claim([$bucket], 2);

        $change = $this->provisioner()->changeLimit($this->organizer, $bucket, 9, 'bigger room');

        self::assertSame(5, $change->previousLimit);
        self::assertSame(9, $change->newLimit);
        self::assertSame(2, $change->held);
        self::assertSame(0, $change->confirmed);
        self::assertFalse($change->belowOccupancy);
        self::assertSame(9, $this->counters($bucket)['limit']);
    }

    public function test_lowering_below_the_occupancy_is_allowed_flagged_and_cancels_nothing_cap_06(): void
    {
        $bucket = $this->bucket(5);
        $participation = $this->claim([$bucket], 3);

        $change = $this->provisioner()->changeLimit($this->organizer, $bucket, 1, 'venue shrank');

        self::assertTrue($change->belowOccupancy);
        self::assertSame(['limit' => 1, 'held' => 3, 'confirmed' => 0, 'overbooked_allowed' => true], $this->counters($bucket));
        self::assertSame('held', $this->claimStatus($participation, $bucket), 'nothing is cancelled');
    }

    public function test_lowering_exactly_to_the_occupancy_is_not_below_it(): void
    {
        $bucket = $this->bucket(5);
        $this->claim([$bucket], 3);

        $change = $this->provisioner()->changeLimit($this->organizer, $bucket, 3, 'fits exactly');

        self::assertFalse($change->belowOccupancy);
        self::assertFalse($this->counters($bucket)['overbooked_allowed']);
    }

    public function test_raising_the_limit_again_clears_the_overbooked_flag(): void
    {
        $bucket = $this->bucket(5);
        $this->claim([$bucket], 3);
        $this->provisioner()->changeLimit($this->organizer, $bucket, 1, 'shrunk');

        $this->provisioner()->changeLimit($this->organizer, $bucket, 4, 'restored');

        self::assertSame(['limit' => 4, 'held' => 3, 'confirmed' => 0, 'overbooked_allowed' => false], $this->counters($bucket));
    }

    public function test_the_limit_can_be_removed_and_set_again(): void
    {
        $bucket = $this->bucket(2);

        $removed = $this->provisioner()->changeLimit($this->organizer, $bucket, null, 'open end');
        $set = $this->provisioner()->changeLimit($this->organizer, $bucket, 7, 'capped');

        self::assertSame([2, null], [$removed->previousLimit, $removed->newLimit]);
        self::assertSame([null, 7], [$set->previousLimit, $set->newLimit]);
        self::assertFalse($set->belowOccupancy);
    }

    public function test_an_unknown_or_foreign_bucket_is_not_found(): void
    {
        $bucket = $this->bucket(2);

        try {
            $this->provisioner()->changeLimit(OrganizerId::generate(), $bucket, 9, 'not mine');
            self::fail('A foreign organizer must not change the limit.');
        } catch (CapacityBucketNotFound) {
        }

        $this->expectException(CapacityBucketNotFound::class);
        $this->provisioner()->changeLimit($this->organizer, CapacityBucketId::generate(), 9, 'unknown');
    }

    public function test_a_negative_new_limit_is_rejected(): void
    {
        $bucket = $this->bucket(2);

        $this->expectException(\InvalidArgumentException::class);
        $this->provisioner()->changeLimit($this->organizer, $bucket, -3, 'nonsense');
    }
}
