<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Query;

/**
 * Typed access to DBAL rows of the content schema.
 */
final class Row
{
    /**
     * @param array<string, mixed> $row
     */
    public static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        \assert(\is_string($value), $key);

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        \assert(null === $value || \is_string($value), $key);

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        \assert(\is_int($value) || (\is_string($value) && is_numeric($value)), $key);

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function bool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;
        \assert(\is_bool($value), $key);

        return $value;
    }
}
