<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query;

/**
 * Typed access to a DBAL row: the query services turn `mixed` into the types of their views in one place.
 */
final class Row
{
    /**
     * @param array<string, mixed> $row
     */
    public static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) || \is_int($value) ? (string) $value : '';
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        return \is_int($value) || \is_string($value) ? (int) $value : 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function date(array $row, string $key): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::string($row, $key));
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableDate(array $row, string $key): ?\DateTimeImmutable
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
