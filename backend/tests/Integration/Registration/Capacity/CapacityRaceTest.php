<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration\Capacity;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Eventor\Registration\Application\Capacity\Query\CapacityConsistencyCheck;
use Eventor\Registration\Contract\CapacityBucketSpec;
use Eventor\Registration\Contract\CapacityProvisioner;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Support\Concurrency\ConcurrencyHarness;
use Eventor\Tests\Support\Concurrency\ConcurrencyResults;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * Foundation of S01 / S02 at the capacity level (ADR 002 "Verification"): real operating-system processes, real
 * commits, no dama rollback. The order handlers of the later waves reuse the same harness with their own command.
 *
 * Rows are removed in tearDown; the application role has no TRUNCATE privilege, so DELETE (claims first) does it.
 */
#[SkipDatabaseRollback]
final class CapacityRaceTest extends IntegrationTestCase
{
    private OrganizerId $organizer;
    private EventId $event;

    /** @var list<CapacityBucketId> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = OrganizerId::generate();
        $this->event = EventId::generate();
        $this->created = [];
    }

    protected function tearDown(): void
    {
        $ids = array_map(static fn(CapacityBucketId $id): string => $id->toString(), $this->created);
        if ([] !== $ids) {
            $in = implode(',', array_fill(0, \count($ids), '?'));
            $this->connection()->executeStatement("DELETE FROM registration.capacity_claim WHERE bucket_id IN ($in)", $ids);
            $this->connection()->executeStatement("DELETE FROM registration.capacity_bucket WHERE id IN ($in)", $ids);
        }
        // The racing processes committed their order -> person -> participation chain (the claims reference it).
        $organizer = $this->organizer->toString();
        $this->connection()->executeStatement('DELETE FROM registration.participation WHERE organizer_id = ?', [$organizer]);
        $this->connection()->executeStatement('DELETE FROM registration.person WHERE organizer_id = ?', [$organizer]);
        $this->connection()->executeStatement('DELETE FROM registration.orders WHERE organizer_id = ?', [$organizer]);
        parent::tearDown();
    }

    private function bucket(?int $limit, CapacityScope $scope = CapacityScope::Activity): CapacityBucketId
    {
        $id = CapacityBucketId::generate();
        $this->service(CapacityProvisioner::class)->provision($this->organizer, $this->event, [
            new CapacityBucketSpec($id, $scope, EventId::generate()->toString(), $limit, CapacityUnit::Seat),
        ]);
        $this->created[] = $id;

        return $id;
    }

    /**
     * @return array{held: int, confirmed: int}
     */
    private function counters(CapacityBucketId $bucket): array
    {
        return [
            'held' => $this->dbInt('SELECT held FROM registration.capacity_bucket WHERE id = ?', [$bucket->toString()]),
            'confirmed' => $this->dbInt('SELECT confirmed FROM registration.capacity_bucket WHERE id = ?', [$bucket->toString()]),
        ];
    }

    /**
     * @param list<CapacityBucketId>                      $buckets
     * @param callable(list<string>, int): list<string>|null $order how to arrange the bucket options for process $i
     */
    private function race(int $processes, array $buckets, int $seats = 1, string $kind = 'held', ?callable $order = null): ConcurrencyResults
    {
        $ids = array_map(static fn(CapacityBucketId $id): string => $id->toString(), $buckets);

        return ConcurrencyHarness::run(
            $this->connection(),
            $processes,
            'eventor:test:capacity-claim',
            fn(int $i): array => [
                '--organizer='.$this->organizer->toString(),
                ...array_map(static fn(string $id): string => '--bucket='.$id, null === $order ? $ids : $order($ids, $i)),
                '--seats='.$seats,
                '--kind='.$kind,
            ],
        );
    }

    private function assertNoDrift(): void
    {
        $check = $this->service(CapacityConsistencyCheck::class);
        self::assertSame([], $check->drift(), 'counters must equal the sums of the claims');
    }

    public function test_eight_processes_racing_for_the_last_seat_produce_exactly_one_winner_s01(): void
    {
        $bucket = $this->bucket(1);

        $results = $this->race(8, [$bucket]);

        self::assertCount(1, $results->succeeded(), $results->describe());
        self::assertCount(7, $results->failedWith('capacity_exhausted'), $results->describe());
        self::assertSame([], $results->unexpected(['capacity_exhausted']), $results->describe());
        $counters = $this->counters($bucket);
        self::assertSame(1, $counters['held'] + $counters['confirmed'], 'held + confirmed = limit');
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM registration.capacity_claim WHERE bucket_id = ?', [$bucket->toString()]));
        $this->assertNoDrift();
    }

    public function test_a_bucket_with_k_seats_admits_exactly_k_of_n_racers(): void
    {
        $bucket = $this->bucket(3);

        $results = $this->race(8, [$bucket], kind: 'confirmed');

        self::assertCount(3, $results->succeeded(), $results->describe());
        self::assertCount(5, $results->failedWith('capacity_exhausted'), $results->describe());
        self::assertSame(['held' => 0, 'confirmed' => 3], $this->counters($bucket));
        $this->assertNoDrift();
    }

    public function test_an_order_over_a_free_and_a_full_bucket_leaves_no_trace_s02(): void
    {
        $free = $this->bucket(100, CapacityScope::Event);
        $lastSeat = $this->bucket(1, CapacityScope::Activity);

        $results = $this->race(8, [$free, $lastSeat]);

        self::assertCount(1, $results->succeeded(), $results->describe());
        self::assertCount(7, $results->failedWith('capacity_exhausted'), $results->describe());
        self::assertSame(['held' => 1, 'confirmed' => 0], $this->counters($free), 'the 7 rolled-back orders must not have left an increment in the free bucket');
        self::assertSame(['held' => 1, 'confirmed' => 0], $this->counters($lastSeat));
        $this->assertNoDrift();
    }

    public function test_a_full_bucket_rejects_every_order_and_the_free_bucket_stays_untouched_s02(): void
    {
        $free = $this->bucket(100, CapacityScope::Event);
        $full = $this->bucket(0, CapacityScope::Activity);

        $results = $this->race(6, [$free, $full]);

        self::assertCount(0, $results->succeeded(), $results->describe());
        self::assertCount(6, $results->failedWith('capacity_exhausted'), $results->describe());
        self::assertSame(['held' => 0, 'confirmed' => 0], $this->counters($free));
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM registration.capacity_claim WHERE bucket_id = ?', [$free->toString()]));
    }

    public function test_orders_listing_the_same_buckets_in_opposite_order_do_not_deadlock(): void
    {
        $a = $this->bucket(4, CapacityScope::Event);
        $b = $this->bucket(4, CapacityScope::Activity);

        $results = $this->race(8, [$a, $b], order: static fn(array $ids, int $i): array => 0 === $i % 2 ? $ids : array_reverse($ids));

        self::assertSame([], $results->unexpected(['capacity_exhausted']), 'a deadlock or any other error would show up here: '.$results->describe());
        self::assertCount(4, $results->succeeded(), $results->describe());
        self::assertCount(4, $results->failedWith('capacity_exhausted'), $results->describe());
        self::assertSame(['held' => 4, 'confirmed' => 0], $this->counters($a));
        self::assertSame(['held' => 4, 'confirmed' => 0], $this->counters($b));
        $this->assertNoDrift();
    }
}
