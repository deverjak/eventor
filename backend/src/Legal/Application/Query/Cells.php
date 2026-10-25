<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Query;

/**
 * Typed access to the untyped cells of a DBAL row (PHPStan max cannot know column types).
 */
final class Cells
{
    public static function string(mixed $value): string
    {
        return \is_scalar($value) || $value instanceof \Stringable ? (string) $value : throw new \UnexpectedValueException('A string cell was expected.');
    }

    public static function int(mixed $value): int
    {
        return \is_int($value) || (\is_string($value) && is_numeric($value)) ? (int) $value : throw new \UnexpectedValueException('An integer cell was expected.');
    }

    public static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }
}
