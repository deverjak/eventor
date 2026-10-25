<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query\View;

use Eventor\Shared\Domain\Money;

/**
 * An amount in the admin API: `{minor, currency}` exactly (no display formatting, the frontend formats).
 */
final readonly class MoneyView
{
    public function __construct(
        public int $minor,
        public string $currency,
    ) {}

    public static function of(Money $money): self
    {
        return new self($money->minor, $money->currency->value);
    }
}
