<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

/**
 * Typed access to the untyped rows of DBAL queries.
 */
final class Hydrate
{
    /** @param array<string, mixed> $row */
    public static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    /** @param array<string, mixed> $row */
    public static function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return null === $value ? null : (\is_scalar($value) ? (string) $value : null);
    }

    /** @param array<string, mixed> $row */
    public static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /** @param array<string, mixed> $row */
    public static function nullableInt(array $row, string $key): ?int
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $row */
    public static function bool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;

        return true === $value || 't' === $value || '1' === $value || 1 === $value;
    }

    /** @param array<string, mixed> $row */
    public static function time(array $row, string $key): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::string($row, $key));
    }

    /** @param array<string, mixed> $row */
    public static function nullableTime(array $row, string $key): ?\DateTimeImmutable
    {
        $value = self::nullableString($row, $key);

        return null === $value ? null : new \DateTimeImmutable($value);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<array-key, mixed>
     */
    public static function json(array $row, string $key): array
    {
        $decoded = json_decode(self::string($row, $key), true);

        return \is_array($decoded) ? $decoded : [];
    }
}
