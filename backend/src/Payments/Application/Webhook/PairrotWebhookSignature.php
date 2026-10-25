<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

/**
 * The signature scheme of Pairrot webhooks (docs/webhooks-receiver.md of Pairrot): HMAC-SHA256 over
 * `"<delivery id>.<timestamp>." + raw body`, key = the 32 bytes behind `pwhsec_` (base64url without padding),
 * header `Pairrot-Webhook-Signature: v1,<key-id>,<base64>` with a second entry, separated by a space, during a rotation.
 * Pure functions, no framework: tested against the published vector.
 */
final class PairrotWebhookSignature
{
    public const int TOLERANCE_SECONDS = 300;
    public const string SECRET_PREFIX = 'pwhsec_';

    /**
     * @return string the 32 raw key bytes
     *
     * @throws \InvalidArgumentException when the secret is not a `pwhsec_` value of 32 bytes
     */
    public static function decodeSecret(string $secret): string
    {
        if (!str_starts_with($secret, self::SECRET_PREFIX)) {
            throw new \InvalidArgumentException('A Pairrot webhook secret starts with "pwhsec_".');
        }

        $encoded = strtr(substr($secret, \strlen(self::SECRET_PREFIX)), '-_', '+/');
        $encoded .= str_repeat('=', (4 - \strlen($encoded) % 4) % 4);
        $key = base64_decode($encoded, true);
        if (false === $key || 32 !== \strlen($key)) {
            throw new \InvalidArgumentException('A Pairrot webhook secret decodes to 32 bytes.');
        }

        return $key;
    }

    /**
     * @return string standard Base64 of the HMAC
     */
    public static function compute(string $deliveryId, string $timestamp, string $body, string $key): string
    {
        return base64_encode(hash_hmac('sha256', $deliveryId.'.'.$timestamp.'.'.$body, $key, true));
    }

    /**
     * The `Pairrot-Webhook-Signature` header value for one key.
     */
    public static function header(string $deliveryId, string $timestamp, string $body, string $keyId, string $key): string
    {
        return 'v1,'.$keyId.','.self::compute($deliveryId, $timestamp, $body, $key);
    }

    /**
     * @param array<string, string> $keys configured keys: id => raw bytes; an id starting with `*` accepts any key id in the header
     */
    public static function verify(array $keys, string $deliveryId, string $timestamp, string $header, string $body, \DateTimeImmutable $now): bool
    {
        if ('' === $deliveryId || 1 !== preg_match('/^\d{1,12}$/D', $timestamp) || [] === $keys) {
            return false;
        }
        if (abs($now->getTimestamp() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $matched = false;
        foreach (explode(' ', preg_replace('/\s+/', ' ', trim($header)) ?? '') as $entry) {
            $parts = explode(',', $entry, 3);
            if (3 !== \count($parts) || 'v1' !== $parts[0]) {
                continue;
            }
            [, $keyId, $signature] = $parts;
            foreach ($keys as $configuredId => $key) {
                if ('*' !== $configuredId[0] && $configuredId !== $keyId) {
                    continue;
                }
                // No early return: every candidate is compared, whatever matched before.
                $matched = hash_equals(self::compute($deliveryId, $timestamp, $body, $key), $signature) || $matched;
            }
        }

        return $matched;
    }
}
