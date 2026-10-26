<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query;

/**
 * Typed access to the untyped cells of a DBAL row (PHPStan max cannot know column types).
 */
final class Cells
{
    public static function string(mixed $value): string
    {
        return \is_scalar($value) || $value instanceof \Stringable ? (string) $value : throw new \UnexpectedValueException('A string cell was expected.');
    }

    public static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    public static function int(mixed $value): int
    {
        return \is_int($value) || (\is_string($value) && is_numeric($value)) ? (int) $value : throw new \UnexpectedValueException('An integer cell was expected.');
    }

    public static function bool(mixed $value): bool
    {
        return true === $value || 't' === $value || 1 === $value || '1' === $value;
    }

    public static function time(mixed $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::string($value));
    }

    public static function nullableTime(mixed $value): ?\DateTimeImmutable
    {
        return null === $value ? null : self::time($value);
    }
}
