<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Formatting;

use Eventor\Shared\Domain\Money;

/**
 * The single place that turns Money into display text ("1 200 Kč", cs_CZ). The API returns minor units and this
 * string; e-mails and PDFs use the same formatter.
 */
final readonly class MoneyFormatter
{
    public function __construct(private string $locale = 'cs_CZ') {}

    public function format(Money $money): string
    {
        $formatter = new \NumberFormatter($this->locale, \NumberFormatter::CURRENCY);
        $factor = $money->currency->minorUnitsPerMajor();

        // Integer arithmetic until the very last step: whole units plus the remainder.
        $major = intdiv(abs($money->minor), $factor);
        $rest = abs($money->minor) % $factor;
        $amount = $major + ($rest / $factor);
        // "1 200 Kč", not "1 200,00 Kč": halers are shown only when there are some.
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0 === $rest ? 0 : 2);
        $text = $formatter->formatCurrency($money->minor < 0 ? -$amount : $amount, $money->currency->value);

        return false === $text ? \sprintf('%d %s', $money->minor, $money->currency->value) : $text;
    }
}
