<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query;

use Eventor\Payments\Application\Query\View\MoneyView;

/**
 * Typed access to the untyped values of a DBAL row, shared by the query services of this module.
 */
final class RowReader
{
    public static function int(mixed $value): int
    {
        return \is_int($value) || \is_string($value) || \is_float($value) ? (int) $value : 0;
    }

    public static function string(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    public static function nullableString(mixed $value): ?string
    {
        return \is_scalar($value) ? (string) $value : null;
    }

    public static function bool(mixed $value): bool
    {
        return true === $value || 't' === $value || 1 === $value || '1' === $value;
    }

    public static function money(mixed $minor, mixed $currency): MoneyView
    {
        return new MoneyView(self::int($minor), self::string($currency));
    }

    /** ISO 8601 with offset, as every time in the admin API. */
    public static function time(mixed $value): ?string
    {
        return \is_string($value) ? (new \DateTimeImmutable($value))->format(\DATE_ATOM) : null;
    }
}
