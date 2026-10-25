<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity\Support;

/**
 * Typed navigation of decoded JSON bodies in tests (PHPStan max forbids offset access on `mixed`). A missing key or a
 * wrong type throws, which fails the test with a readable message.
 */
final class Json
{
    public static function at(mixed $data, string|int ...$path): mixed
    {
        $walked = '$';
        foreach ($path as $key) {
            $walked .= '['.$key.']';
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                throw new \UnexpectedValueException("Nothing at $walked in ".json_encode($data));
            }
            $data = $data[$key];
        }

        return $data;
    }

    public static function str(mixed $data, string|int ...$path): string
    {
        $value = self::at($data, ...$path);

        return \is_string($value) ? $value : throw new \UnexpectedValueException('Expected a string, got '.get_debug_type($value));
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function arr(mixed $data, string|int ...$path): array
    {
        $value = self::at($data, ...$path);

        return \is_array($value) ? $value : throw new \UnexpectedValueException('Expected an array, got '.get_debug_type($value));
    }

    /**
     * A list of objects (e.g. the `items` of a list response), re-indexed.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(mixed $data, string|int ...$path): array
    {
        $rows = [];
        foreach (self::arr($data, ...$path) as $row) {
            if (!\is_array($row)) {
                throw new \UnexpectedValueException('Expected a list of objects');
            }
            /** @var array<string, mixed> $row */
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The row of a list whose `$key` equals `$value`.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    public static function where(array $rows, string $key, mixed $value): array
    {
        foreach ($rows as $row) {
            if (($row[$key] ?? null) === $value) {
                return $row;
            }
        }

        throw new \UnexpectedValueException("No row with $key = ".json_encode($value));
    }
}
