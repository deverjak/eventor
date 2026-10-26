<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Pricing;

use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Money;

/**
 * The price of an order (REG-05). P0: the sum of the variant prices, one line per participation. The chain of
 * `PriceModifier`s of the book (add-ons, early bird, multiple-participation discount, promo code) is P1 and plugs in
 * between the lines and the total.
 */
final class PriceCalculator
{
    /**
     * @param list<Money> $linePrices one per participation, in order
     */
    public static function total(array $linePrices, Currency $currency): Money
    {
        $total = Money::zero($currency);
        foreach ($linePrices as $price) {
            $total = $total->add($price);
        }

        return $total;
    }
}
