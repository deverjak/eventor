<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Registration\Capacity;

use Eventor\Registration\Domain\Capacity\BucketCounters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BucketCountersTest extends TestCase
{
    public function test_an_unlimited_bucket_accepts_anything_and_has_no_free_count(): void
    {
        $bucket = new BucketCounters(null, 1000, 1000);

        self::assertTrue($bucket->accepts(5000));
        self::assertNull($bucket->free());
        self::assertFalse($bucket->isOverbooked());
    }

    /**
     * @return iterable<string, array{int|null, int, int, int, bool}>
     */
    public static function acceptance(): iterable
    {
        yield 'exactly the last seat' => [10, 6, 3, 1, true];
        yield 'one over the limit' => [10, 6, 3, 2, false];
        yield 'held and confirmed both count' => [10, 5, 5, 1, false];
        yield 'empty bucket takes the whole limit' => [4, 0, 0, 4, true];
        yield 'zero limit takes nothing' => [0, 0, 0, 1, false];
    }

    #[DataProvider('acceptance')]
    public function test_the_claim_predicate(?int $limit, int $held, int $confirmed, int $seats, bool $expected): void
    {
        self::assertSame($expected, (new BucketCounters($limit, $held, $confirmed))->accepts($seats));
    }

    public function test_free_never_goes_negative_when_overbooked(): void
    {
        $bucket = new BucketCounters(3, 4, 2);

        self::assertTrue($bucket->isOverbooked());
        self::assertSame(0, $bucket->free());
        self::assertFalse($bucket->accepts(1));
    }

    public function test_lowering_below_the_occupancy_is_flagged_but_to_the_occupancy_is_not(): void
    {
        $bucket = new BucketCounters(10, 4, 3);

        self::assertTrue($bucket->isBelowOccupancyAfter(6));
        self::assertFalse($bucket->isBelowOccupancyAfter(7));
        self::assertFalse($bucket->isBelowOccupancyAfter(null));
    }

    public function test_seats_freed_by_a_raised_limit(): void
    {
        $bucket = new BucketCounters(10, 6, 3); // 1 free

        self::assertSame(4, $bucket->seatsFreedByLimit(14));
        self::assertSame(0, $bucket->seatsFreedByLimit(10));
        self::assertSame(0, $bucket->seatsFreedByLimit(8), 'a lower limit frees nothing');
        self::assertSame(1, $bucket->seatsFreedByLimit(null), 'removing the limit frees "at least one"');
    }

    public function test_a_still_overbooked_bucket_frees_nothing_when_the_limit_rises_a_little(): void
    {
        $bucket = new BucketCounters(3, 4, 2); // overbooked by 3

        self::assertSame(0, $bucket->seatsFreedByLimit(5));
        self::assertSame(2, $bucket->seatsFreedByLimit(8));
    }

    public function test_negative_numbers_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BucketCounters(-1, 0, 0);
    }
}
