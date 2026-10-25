<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Application\DeviceToken\DeviceTokenFormat;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Symfony `access_token` handler of the `check_in` firewall (ADR 006): `Authorization: Bearer dev1_<id>_<secret>`.
 * The lookup is a DBAL query by token id (there is no tenant yet, the token is what names it); the secret is compared
 * as SHA-256 in constant time, expiry and revocation are checked. Every failure is the same `BadCredentialsException`.
 */
final readonly class DeviceTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {}

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $parsed = DeviceTokenFormat::parse($accessToken);
        if (null === $parsed) {
            throw new BadCredentialsException('Invalid device token.');
        }

        /** @var array{id: string, organizer_id: string, check_in_list_id: string|null, label: string, secret_sha256: string, expires_at: string|null, revoked_at: string|null}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id, organizer_id, check_in_list_id, label, secret_sha256, expires_at, revoked_at FROM identity.device_token WHERE id = :id',
            ['id' => $parsed['id']->toString()],
        );

        // Compare against a dummy hash for unknown ids, so unknown and wrong tokens take the same time.
        $expected = false === $row ? str_repeat('0', 64) : $row['secret_sha256'];
        $matches = hash_equals($expected, DeviceTokenFormat::hash($parsed['secret']));
        if (false === $row || !$matches || null !== $row['revoked_at'] || (null !== $row['expires_at'] && new \DateTimeImmutable($row['expires_at']) <= $this->clock->now())) {
            throw new BadCredentialsException('Invalid device token.');
        }

        $user = new DeviceUser(
            $row['id'],
            OrganizerId::fromString($row['organizer_id']),
            null === $row['check_in_list_id'] ? null : CheckInListId::fromString($row['check_in_list_id']),
            $row['label'],
        );

        return new UserBadge($user->getUserIdentifier(), static fn(): DeviceUser => $user);
    }
}
