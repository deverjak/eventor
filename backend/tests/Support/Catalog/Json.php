<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Catalog;

/**
 * Typed access to decoded JSON responses in tests: a dotted path (`activities.0.variants.0.price.minor`) instead of a
 * chain of offsets on `mixed`, and accessors that assert the type they return. A missing path fails the test with the
 * path in the message.
 */
final class Json
{
    public static function at(mixed $data, string $path): mixed
    {
        $current = $data;
        foreach ('' === $path ? [] : explode('.', $path) as $segment) {
            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                throw new \OutOfBoundsException(\sprintf('The path "%s" does not exist (stopped at "%s").', $path, $segment));
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public static function has(mixed $data, string $path): bool
    {
        try {
            self::at($data, $path);

            return true;
        } catch (\OutOfBoundsException) {
            return false;
        }
    }

    public static function str(mixed $data, string $path = ''): string
    {
        $value = self::at($data, $path);
        if (!\is_string($value) && !\is_int($value)) {
            throw new \UnexpectedValueException(\sprintf('The path "%s" is not a string: %s', $path, get_debug_type($value)));
        }

        return (string) $value;
    }

    public static function int(mixed $data, string $path = ''): int
    {
        $value = self::at($data, $path);
        if (!\is_int($value)) {
            throw new \UnexpectedValueException(\sprintf('The path "%s" is not an int: %s', $path, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function arr(mixed $data, string $path = ''): array
    {
        $value = self::at($data, $path);
        if (!\is_array($value)) {
            throw new \UnexpectedValueException(\sprintf('The path "%s" is not an array: %s', $path, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * `array_column()` of a decoded list.
     *
     * @return array<array-key, mixed>
     */
    public static function column(mixed $rows, string|int|null $key, string|int|null $index = null): array
    {
        if (!\is_array($rows)) {
            throw new \UnexpectedValueException('Not a list of rows: '.get_debug_type($rows));
        }

        return array_column($rows, $key, $index);
    }

    /**
     * The values of one key of every row at `$path`.
     *
     * @return array<array-key, mixed>
     */
    public static function pluck(mixed $data, string $path, string $key): array
    {
        return self::column(self::at($data, $path), $key);
    }
}
