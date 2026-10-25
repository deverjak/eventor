<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration\Capacity;

use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Registration\Contract\Exception\CapacityExhausted;
use Eventor\Registration\Domain\Capacity\CapacityInvariantViolated;
use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Registration\Domain\Capacity\SeatClaim;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * ADR 002 on a real database: the conditional UPDATE, all-or-nothing over several buckets, and the moves
 * confirm / release / reclaim. The surrounding "transaction" is the dama rollback; the tests that need a rollback
 * inside the test wrap the call in `transactional()` the way the command bus does.
 */
final class CapacityAllocatorTest extends CapacityTestCase
{
    public function test_a_held_claim_lands_in_held_and_is_recorded(): void
    {
        $bucket = $this->bucket(5);

        $participation = $this->claim([$bucket], 2);

        self::assertSame(['limit' => 5, 'held' => 2, 'confirmed' => 0, 'overbooked_allowed' => false], $this->counters($bucket));
        self::assertSame('held', $this->claimStatus($participation, $bucket));
        $this->assertNoDrift();
    }

    public function test_a_confirmed_claim_lands_in_confirmed(): void
    {
        $bucket = $this->bucket(5);

        $participation = $this->claim([$bucket], 1, ClaimKind::Confirmed);

        self::assertSame(['limit' => 5, 'held' => 0, 'confirmed' => 1, 'overbooked_allowed' => false], $this->counters($bucket));
        self::assertSame('confirmed', $this->claimStatus($participation, $bucket));
    }

    public function test_the_last_seat_is_taken_and_the_next_claim_gets_capacity_exhausted_with_the_waitlist_step(): void
    {
        $bucket = $this->bucket(2, CapacityScope::Variant);
        $this->claim([$bucket]);
        $this->claim([$bucket], 1, ClaimKind::Confirmed);

        try {
            $this->claim([$bucket]);
            self::fail('The bucket is full.');
        } catch (CapacityExhausted $e) {
            self::assertTrue($e->bucketId->equals($bucket));
            self::assertSame(CapacityScope::Variant, $e->scope);
            self::assertTrue($e->waitlistAvailable);
            self::assertSame([['action' => 'join_waitlist']], $e->nextSteps());
        }

        self::assertSame(['limit' => 2, 'held' => 1, 'confirmed' => 1, 'overbooked_allowed' => false], $this->counters($bucket));
    }

    public function test_the_waitlist_step_can_be_switched_off(): void
    {
        $bucket = $this->bucket(1);
        $this->claim([$bucket]);

        try {
            $this->allocator()->claim($this->organizer, [new SeatClaim(ParticipationId::generate(), $bucket, 1, ClaimKind::Held)], waitlistAvailable: false);
            self::fail('The bucket is full.');
        } catch (CapacityExhausted $e) {
            self::assertSame([], $e->nextSteps());
        }
    }

    public function test_seats_of_all_participations_of_an_order_are_summed_before_the_check(): void
    {
        $bucket = $this->bucket(2);

        $this->expectException(CapacityExhausted::class);
        $this->allocator()->claim($this->organizer, [
            new SeatClaim(ParticipationId::generate(), $bucket, 1, ClaimKind::Held),
            new SeatClaim(ParticipationId::generate(), $bucket, 1, ClaimKind::Held),
            new SeatClaim(ParticipationId::generate(), $bucket, 1, ClaimKind::Held),
        ]);
    }

    public function test_an_order_over_a_free_and_a_full_bucket_changes_nothing_s02(): void
    {
        $free = $this->bucket(10, CapacityScope::Event);
        $full = $this->bucket(1, CapacityScope::Activity);
        $this->claim([$full]);
        $before = [$this->counters($free), $this->counters($full)];
        $claimsBefore = $this->dbInt('SELECT count(*) FROM registration.capacity_claim');

        try {
            // What the command bus does: one transaction around the whole handler.
            $this->connection()->transactional(fn() => $this->claim([$free, $full]));
            self::fail('The activity is full.');
        } catch (CapacityExhausted $e) {
            self::assertTrue($e->bucketId->equals($full));
        }

        self::assertSame($before, [$this->counters($free), $this->counters($full)], 'the increment of the free bucket must be rolled back with the failed order');
        self::assertSame($claimsBefore, $this->dbInt('SELECT count(*) FROM registration.capacity_claim'));
        $this->assertNoDrift();
    }

    public function test_a_claim_succeeds_over_all_levels_at_once(): void
    {
        $event = $this->bucket(100, CapacityScope::Event);
        $activity = $this->bucket(10, CapacityScope::Activity);
        $variant = $this->bucket(3, CapacityScope::Variant);

        $this->claim([$variant, $event, $activity]);

        foreach ([$event, $activity, $variant] as $bucket) {
            self::assertSame(1, $this->counters($bucket)['held']);
        }
        $this->assertNoDrift();
    }

    public function test_an_unlimited_bucket_accepts_any_number_of_seats(): void
    {
        $bucket = $this->bucket(null, CapacityScope::Event);

        $this->claim([$bucket], 500);

        self::assertSame(500, $this->counters($bucket)['held']);
    }

    public function test_an_unknown_bucket_and_a_foreign_organizers_bucket_are_not_found(): void
    {
        $mine = $this->bucket(5);

        try {
            $this->claim([CapacityBucketId::generate()]);
            self::fail('No such bucket.');
        } catch (CapacityBucketNotFound) {
        }

        try {
            $this->allocator()->claim(OrganizerId::generate(), [new SeatClaim(ParticipationId::generate(), $mine, 1, ClaimKind::Held)]);
            self::fail('Another organizer must not touch the bucket.');
        } catch (CapacityBucketNotFound) {
        }

        self::assertSame(0, $this->counters($mine)['held']);
    }

    public function test_a_participation_cannot_claim_a_bucket_it_already_has_a_claim_on(): void
    {
        $bucket = $this->bucket(5);
        $participation = $this->claim([$bucket]);

        $this->expectException(CapacityInvariantViolated::class);
        $this->claim([$bucket], 1, ClaimKind::Held, $participation);
    }

    public function test_confirm_moves_held_to_confirmed_on_every_bucket_and_is_idempotent(): void
    {
        $activity = $this->bucket(5, CapacityScope::Activity);
        $variant = $this->bucket(5, CapacityScope::Variant);
        $participation = $this->claim([$activity, $variant], 2);

        $this->allocator()->confirm($this->organizer, $participation);
        $this->allocator()->confirm($this->organizer, $participation);

        foreach ([$activity, $variant] as $bucket) {
            self::assertSame(['limit' => 5, 'held' => 0, 'confirmed' => 2, 'overbooked_allowed' => false], $this->counters($bucket));
            self::assertSame('confirmed', $this->claimStatus($participation, $bucket));
        }
        $this->assertNoDrift();
    }

    public function test_confirm_without_claims_or_after_release_is_an_invariant_violation(): void
    {
        $bucket = $this->bucket(5);
        $released = $this->claim([$bucket]);
        $this->allocator()->release($this->organizer, [$released]);

        try {
            $this->allocator()->confirm($this->organizer, ParticipationId::generate());
            self::fail('No claims.');
        } catch (CapacityInvariantViolated) {
        }

        $this->expectException(CapacityInvariantViolated::class);
        $this->allocator()->confirm($this->organizer, $released);
    }

    public function test_release_gives_back_held_and_confirmed_seats_and_reports_them(): void
    {
        $activity = $this->bucket(5, CapacityScope::Activity);
        $variant = $this->bucket(5, CapacityScope::Variant);
        $pending = $this->claim([$activity, $variant]);
        $paid = $this->claim([$activity, $variant], 1, ClaimKind::Confirmed);

        $released = $this->allocator()->release($this->organizer, [$pending, $paid]);

        self::assertSame(2, $released->seats);
        $ids = array_map(static fn(CapacityBucketId $id): string => $id->toString(), $released->bucketIds);
        $expected = [$activity->toString(), $variant->toString()];
        sort($expected, \SORT_STRING);
        self::assertSame($expected, $ids, 'buckets come back in lock order');
        foreach ([$activity, $variant] as $bucket) {
            self::assertSame(['limit' => 5, 'held' => 0, 'confirmed' => 0, 'overbooked_allowed' => false], $this->counters($bucket));
        }
        self::assertSame('released', $this->claimStatus($pending, $activity));
        $this->assertNoDrift();
    }

    public function test_release_is_idempotent(): void
    {
        $bucket = $this->bucket(5);
        $participation = $this->claim([$bucket], 2);
        $this->allocator()->release($this->organizer, [$participation]);

        $again = $this->allocator()->release($this->organizer, [$participation]);

        self::assertTrue($again->isEmpty());
        self::assertSame(0, $again->seats);
        self::assertSame(0, $this->counters($bucket)['held']);
    }

    public function test_release_of_nothing_is_empty(): void
    {
        self::assertTrue($this->allocator()->release($this->organizer, [])->isEmpty());
        self::assertTrue($this->allocator()->release($this->organizer, [ParticipationId::generate()])->isEmpty());
    }

    public function test_a_foreign_organizer_cannot_release_the_seats(): void
    {
        $bucket = $this->bucket(5);
        $participation = $this->claim([$bucket]);

        $released = $this->allocator()->release(OrganizerId::generate(), [$participation]);

        self::assertTrue($released->isEmpty());
        self::assertSame(1, $this->counters($bucket)['held']);
    }

    public function test_an_overbooked_bucket_takes_no_claims_and_clears_its_flag_only_when_back_under_the_limit(): void
    {
        $bucket = $this->bucket(3);
        $a = $this->claim([$bucket]);
        $b = $this->claim([$bucket]);
        $c = $this->claim([$bucket]);
        $this->provisioner()->changeLimit($this->organizer, $bucket, 1, 'smaller venue');
        self::assertTrue($this->counters($bucket)['overbooked_allowed']);

        try {
            $this->claim([$bucket]);
            self::fail('An overbooked bucket takes nothing new.');
        } catch (CapacityExhausted) {
        }

        $this->allocator()->release($this->organizer, [$a]); // 2 seats occupied, limit 1: still overbooked
        self::assertTrue($this->counters($bucket)['overbooked_allowed']);
        $this->allocator()->release($this->organizer, [$b]); // 1 seat = limit: the flag goes
        self::assertSame(['limit' => 1, 'held' => 1, 'confirmed' => 0, 'overbooked_allowed' => false], $this->counters($bucket));
        $this->allocator()->release($this->organizer, [$c]);
        $this->assertNoDrift();
    }

    public function test_reclaim_takes_released_seats_back_as_confirmed_when_there_is_room(): void
    {
        $activity = $this->bucket(1, CapacityScope::Activity);
        $variant = $this->bucket(1, CapacityScope::Variant);
        $participation = $this->claim([$activity, $variant]);
        $this->allocator()->release($this->organizer, [$participation]);

        self::assertTrue($this->allocator()->canReclaim($this->organizer, $participation));
        $this->allocator()->reclaim($this->organizer, $participation);

        foreach ([$activity, $variant] as $bucket) {
            self::assertSame(['limit' => 1, 'held' => 0, 'confirmed' => 1, 'overbooked_allowed' => false], $this->counters($bucket));
            self::assertSame('confirmed', $this->claimStatus($participation, $bucket));
        }
        self::assertFalse($this->allocator()->canReclaim($this->organizer, $participation), 'nothing is released any more');
        $this->assertNoDrift();
    }

    public function test_reclaim_fails_and_changes_nothing_when_one_bucket_is_full_again_s05(): void
    {
        $roomy = $this->bucket(5, CapacityScope::Event);
        $tight = $this->bucket(1, CapacityScope::Activity);
        $latecomer = $this->claim([$roomy, $tight]);
        $this->allocator()->release($this->organizer, [$latecomer]);
        $this->claim([$tight]); // somebody else took the seat

        self::assertFalse($this->allocator()->canReclaim($this->organizer, $latecomer));
        try {
            $this->connection()->transactional(fn() => $this->allocator()->reclaim($this->organizer, $latecomer));
            self::fail('The activity is full again.');
        } catch (CapacityExhausted $e) {
            self::assertTrue($e->bucketId->equals($tight));
        }

        self::assertSame('released', $this->claimStatus($latecomer, $roomy));
        self::assertSame(0, $this->counters($roomy)['held'] + $this->counters($roomy)['confirmed']);
        $this->assertNoDrift();
    }

    public function test_reclaim_as_held_and_reclaim_of_an_active_participation(): void
    {
        $bucket = $this->bucket(2);
        $participation = $this->claim([$bucket]);

        $this->allocator()->reclaim($this->organizer, $participation); // nothing released: no-op
        self::assertSame(['limit' => 2, 'held' => 1, 'confirmed' => 0, 'overbooked_allowed' => false], $this->counters($bucket));

        $this->allocator()->release($this->organizer, [$participation]);
        $this->allocator()->reclaim($this->organizer, $participation, ClaimKind::Held);
        self::assertSame(['limit' => 2, 'held' => 1, 'confirmed' => 0, 'overbooked_allowed' => false], $this->counters($bucket));
        self::assertSame('held', $this->claimStatus($participation, $bucket));
    }

    public function test_can_reclaim_is_false_for_unknown_participations_and_for_a_foreign_organizer(): void
    {
        $bucket = $this->bucket(2);
        $participation = $this->claim([$bucket]);
        $this->allocator()->release($this->organizer, [$participation]);

        self::assertFalse($this->allocator()->canReclaim($this->organizer, ParticipationId::generate()));
        self::assertFalse($this->allocator()->canReclaim(OrganizerId::generate(), $participation));
    }

    public function test_the_table_check_is_the_last_line_of_defence(): void
    {
        $bucket = $this->bucket(1);

        try {
            $this->connection()->transactional(fn() => $this->connection()->executeStatement('UPDATE registration.capacity_bucket SET held = 2 WHERE id = ?', [$bucket->toString()]));
            self::fail('The CHECK must reject an unflagged overbooking.');
        } catch (\Doctrine\DBAL\Exception\DriverException) {
        }

        try {
            $this->connection()->transactional(fn() => $this->connection()->executeStatement('UPDATE registration.capacity_bucket SET held = -1 WHERE id = ?', [$bucket->toString()]));
            self::fail('Counters cannot be negative.');
        } catch (\Doctrine\DBAL\Exception\DriverException) {
        }

        self::assertSame(0, $this->counters($bucket)['held']);
    }
}
