<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Presenter;

/**
 * Typed access to the untyped cells of a DBAL row (PHPStan max does not trust `mixed`).
 */
final class Cells
{
    public static function string(mixed $value): string
    {
        return \is_string($value) ? $value : (\is_scalar($value) ? (string) $value : throw new \UnexpectedValueException('Expected a scalar cell.'));
    }

    public static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : self::string($value);
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : throw new \UnexpectedValueException('Expected a numeric cell.');
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
