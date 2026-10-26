<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Http\Request;

use Eventor\Registration\Contract\Exception\InvalidOrderRequest;
use Symfony\Component\Uid\Uuid;

/**
 * Typed reading of a decoded JSON body: every wrong type is a `invalid_order_request` violation with its path, not a PHP
 * warning. Used by the controllers of this module.
 */
final class Payload
{
    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function object(array $data, string $key, string $path = ''): array
    {
        $value = $data[$key] ?? [];
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw InvalidOrderRequest::at($path.$key, 'invalid_type', 'An object was expected.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    public static function objects(array $data, string $key, string $path = ''): array
    {
        $value = $data[$key] ?? [];
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidOrderRequest::at($path.$key, 'invalid_type', 'A list was expected.');
        }
        foreach ($value as $i => $item) {
            if (!\is_array($item) || array_is_list($item) && [] !== $item) {
                throw InvalidOrderRequest::at($path.$key.'.'.$i, 'invalid_type', 'An object was expected.');
            }
        }

        /** @var list<array<string, mixed>> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function string(array $data, string $key, string $path, bool $required = true): ?string
    {
        $value = $data[$key] ?? null;
        if (null === $value) {
            return $required ? throw InvalidOrderRequest::at($path.$key, 'required', 'This value is required.') : null;
        }
        if (!\is_string($value) || '' === trim($value)) {
            return $required ? throw InvalidOrderRequest::at($path.$key, 'invalid_type', 'A non-empty text was expected.') : null;
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function uuid(array $data, string $key, string $path, bool $required = true): ?string
    {
        $value = self::string($data, $key, $path, $required);
        if (null !== $value && !Uuid::isValid($value)) {
            throw InvalidOrderRequest::at($path.$key, 'invalid_uuid', 'A UUID was expected.');
        }

        return $value;
    }
}
