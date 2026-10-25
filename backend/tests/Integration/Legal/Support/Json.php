<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal\Support;

/**
 * Typed reads of decoded JSON for the Legal tests (PHPStan max does not allow offset access on `mixed`).
 */
final class Json
{
    public static function at(mixed $data, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }

        return $data;
    }

    public static function string(mixed $data, string|int ...$path): string
    {
        $value = self::at($data, ...$path);
        if (!\is_string($value)) {
            throw new \UnexpectedValueException('Expected a string at '.implode('.', array_map(strval(...), $path)).', got '.get_debug_type($value));
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function array(mixed $data, string|int ...$path): array
    {
        $value = self::at($data, ...$path);
        if (!\is_array($value)) {
            throw new \UnexpectedValueException('Expected an array at '.implode('.', array_map(strval(...), $path)).', got '.get_debug_type($value));
        }

        return $value;
    }

    /**
     * The values of one key of every item of a list.
     *
     * @return list<mixed>
     */
    public static function column(mixed $list, string $key): array
    {
        return array_values(array_map(static fn(mixed $item): mixed => self::at($item, $key), \is_array($list) ? $list : []));
    }
}
