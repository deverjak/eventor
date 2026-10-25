<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Exception\InvalidMoney;
use Eventor\Shared\Domain\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_adds_and_subtracts_in_minor_units(): void
    {
        $price = Money::of(30_000);

        self::assertSame(45_050, $price->add(Money::of(15_050))->minor);
        self::assertSame(-500, $price->subtract(Money::of(30_500))->minor);
        self::assertSame(Currency::CZK, $price->add(Money::of(1))->currency);
    }

    public function test_it_refuses_to_mix_currencies(): void
    {
        $this->expectException(InvalidMoney::class);

        Money::of(100)->add(Money::of(100, Currency::EUR));
    }

    public function test_it_refuses_to_compare_different_currencies_but_equals_is_false(): void
    {
        self::assertFalse(Money::of(100)->equals(Money::of(100, Currency::EUR)));

        $this->expectException(InvalidMoney::class);
        Money::of(100)->greaterThan(Money::of(1, Currency::EUR));
    }

    public function test_it_detects_integer_overflow(): void
    {
        $this->expectException(InvalidMoney::class);

        Money::of(\PHP_INT_MAX)->add(Money::of(1));
    }

    #[DataProvider('percentages')]
    public function test_percentage_in_basis_points_rounds_half_up(int $minor, int $basisPoints, int $expected): void
    {
        self::assertSame($expected, Money::of($minor)->percentage($basisPoints)->minor);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function percentages(): iterable
    {
        yield '10 % of 300 Kč' => [30_000, 1_000, 3_000];
        yield '15 % of 99,90 Kč rounds .985 up' => [9_990, 1_500, 1_499];
        yield 'half rounds up' => [5, 5_000, 3];
        yield 'just below half rounds down' => [4, 6_250, 3];
        yield 'negative rounds away from zero' => [-5, 5_000, -3];
        yield 'zero percent' => [30_000, 0, 0];
    }

    public function test_allocate_never_loses_a_minor_unit(): void
    {
        $parts = Money::of(10_000)->allocate([1, 1, 1]);

        self::assertSame([3_334, 3_333, 3_333], array_map(static fn(Money $m): int => $m->minor, $parts));
        self::assertSame(10_000, array_sum(array_map(static fn(Money $m): int => $m->minor, $parts)));
    }

    public function test_allocate_by_weights_and_negative_amounts(): void
    {
        self::assertSame([7_000, 3_000], array_map(static fn(Money $m): int => $m->minor, Money::of(10_000)->allocate([7, 3])));
        self::assertSame([-3_334, -3_333, -3_333], array_map(static fn(Money $m): int => $m->minor, Money::of(-10_000)->allocate([1, 1, 1])));
    }

    public function test_allocate_requires_positive_ratios(): void
    {
        $this->expectException(InvalidMoney::class);

        Money::of(100)->allocate([1, 0]);
    }

    public function test_it_serialises_without_floats(): void
    {
        self::assertSame('{"minor":120000,"currency":"CZK"}', json_encode(Money::of(120_000), \JSON_THROW_ON_ERROR));
    }

    public function test_it_is_immutable(): void
    {
        $money = Money::of(100);
        $money->add(Money::of(50));
        $money->multiply(3);

        self::assertSame(100, $money->minor);
        self::assertSame(300, $money->multiply(3)->minor);
    }
}
