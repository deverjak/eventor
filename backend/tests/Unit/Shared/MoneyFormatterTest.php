<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Infrastructure\Formatting\MoneyFormatter;
use PHPUnit\Framework\TestCase;

final class MoneyFormatterTest extends TestCase
{
    public function test_it_formats_czech_crowns_with_a_non_breaking_space(): void
    {
        $formatter = new MoneyFormatter();

        self::assertSame("1\u{a0}200\u{a0}Kč", $formatter->format(Money::of(120_000)));
        self::assertSame("300\u{a0}Kč", $formatter->format(Money::of(30_000)));
        self::assertSame("99,90\u{a0}Kč", $formatter->format(Money::of(9_990)));
        self::assertSame("-5\u{a0}Kč", $formatter->format(Money::of(-500)));
    }

    public function test_it_formats_euros(): void
    {
        self::assertStringContainsString('€', (new MoneyFormatter())->format(Money::of(1_000, Currency::EUR)));
    }
}
