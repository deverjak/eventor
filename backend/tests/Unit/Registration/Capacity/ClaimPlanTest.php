<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Registration\Capacity;

use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Registration\Domain\Capacity\ClaimPlan;
use Eventor\Registration\Domain\Capacity\SeatClaim;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\ParticipationId;
use PHPUnit\Framework\TestCase;

final class ClaimPlanTest extends TestCase
{
    public function test_seats_of_all_participations_are_summed_per_bucket(): void
    {
        $event = CapacityBucketId::generate();
        $activity = CapacityBucketId::generate();
        $parent = ParticipationId::generate();
        $child = ParticipationId::generate();

        $plan = ClaimPlan::of([
            new SeatClaim($parent, $event, 1, ClaimKind::Held),
            new SeatClaim($child, $event, 1, ClaimKind::Held),
            new SeatClaim($parent, $activity, 1, ClaimKind::Held),
            new SeatClaim($child, $activity, 2, ClaimKind::Confirmed),
        ]);

        $totals = [];
        foreach ($plan->buckets as $demand) {
            $totals[$demand->bucketId->toString()] = [$demand->held, $demand->confirmed, $demand->total()];
        }
        self::assertSame([2, 0, 2], $totals[$event->toString()]);
        self::assertSame([1, 2, 3], $totals[$activity->toString()]);
    }

    public function test_buckets_are_in_ascending_id_order_whatever_the_input_order(): void
    {
        $ids = [];
        for ($i = 0; $i < 6; ++$i) {
            $ids[] = CapacityBucketId::generate();
        }
        $participation = ParticipationId::generate();
        $shuffled = $ids;
        shuffle($shuffled);

        $plan = ClaimPlan::of(array_map(static fn(CapacityBucketId $id): SeatClaim => new SeatClaim($participation, $id, 1, ClaimKind::Held), $shuffled));

        $order = array_map(static fn($demand): string => $demand->bucketId->toString(), $plan->buckets);
        $sorted = $order;
        sort($sorted, \SORT_STRING);
        self::assertSame($sorted, $order);
        self::assertSame($order, array_map(static fn(SeatClaim $claim): string => $claim->bucketId->toString(), $plan->claims));
    }

    public function test_two_orders_over_the_same_buckets_get_the_same_lock_order(): void
    {
        $a = CapacityBucketId::generate();
        $b = CapacityBucketId::generate();
        $one = ClaimPlan::of([new SeatClaim(ParticipationId::generate(), $a, 1, ClaimKind::Held), new SeatClaim(ParticipationId::generate(), $b, 1, ClaimKind::Held)]);
        $other = ClaimPlan::of([new SeatClaim(ParticipationId::generate(), $b, 1, ClaimKind::Held), new SeatClaim(ParticipationId::generate(), $a, 1, ClaimKind::Held)]);

        self::assertEquals(
            array_map(static fn($d): string => $d->bucketId->toString(), $one->buckets),
            array_map(static fn($d): string => $d->bucketId->toString(), $other->buckets),
        );
    }

    public function test_a_participation_cannot_claim_the_same_bucket_twice(): void
    {
        $participation = ParticipationId::generate();
        $bucket = CapacityBucketId::generate();

        $this->expectException(\InvalidArgumentException::class);
        ClaimPlan::of([new SeatClaim($participation, $bucket, 1, ClaimKind::Held), new SeatClaim($participation, $bucket, 1, ClaimKind::Confirmed)]);
    }

    public function test_an_empty_claim_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ClaimPlan::of([]);
    }

    public function test_a_claim_needs_at_least_one_seat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SeatClaim(ParticipationId::generate(), CapacityBucketId::generate(), 0, ClaimKind::Held);
    }
}
