<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\DeviceToken;

use Eventor\Identity\Domain\DeviceToken\DeviceTokenId;

/**
 * The wire format of a device token: `dev1_<uuid>_<secret>` (ADR 006). The prefix makes leaked tokens greppable and
 * lets the firewall pick the right authenticator; only SHA-256 of the secret is stored.
 */
final class DeviceTokenFormat
{
    public const string PREFIX = 'dev1_';

    public static function newSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function format(DeviceTokenId $id, string $secret): string
    {
        return self::PREFIX.$id->toString().'_'.$secret;
    }

    /**
     * @return array{id: DeviceTokenId, secret: string}|null
     */
    public static function parse(string $token): ?array
    {
        if (1 !== preg_match('/^dev1_([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})_([A-Za-z0-9_-]{43})$/', $token, $m)) {
            return null;
        }

        return ['id' => DeviceTokenId::fromString($m[1]), 'secret' => $m[2]];
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
