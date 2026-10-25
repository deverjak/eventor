<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;

final class FinancialStateViewTest extends TestCase
{
    public function test_outstanding_is_what_is_left_to_pay_and_never_negative(): void
    {
        $partial = new FinancialStateView(FinancialState::PartiallyPaid, Money::of(30_000), Money::of(10_000));
        $over = new FinancialStateView(FinancialState::Overpaid, Money::of(30_000), Money::of(35_000));

        self::assertTrue($partial->outstanding()->equals(Money::of(20_000)));
        self::assertTrue($over->outstanding()->isZero());
    }
}
