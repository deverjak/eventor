<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Application\Query\View\DeviceTokenView;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;

/**
 * Device tokens of an organizer, never including the secret.
 */
final readonly class DeviceTokenQuery
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {}

    /**
     * @return list<DeviceTokenView>
     */
    public function list(OrganizerId $organizer): array
    {
        /** @var list<array{id: string, label: string, check_in_list_id: string|null, created_at: string, expires_at: string|null, revoked_at: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, label, check_in_list_id, created_at, expires_at, revoked_at
               FROM identity.device_token WHERE organizer_id = :organizer ORDER BY created_at DESC, id',
            ['organizer' => $organizer->toString()],
        );
        $now = $this->clock->now();
        $iso = static fn(?string $value): ?string => null === $value ? null : (new \DateTimeImmutable($value))->format(\DATE_ATOM);

        return array_map(static fn(array $row): DeviceTokenView => new DeviceTokenView(
            $row['id'],
            $row['label'],
            $row['check_in_list_id'],
            (string) $iso($row['created_at']),
            $iso($row['expires_at']),
            $iso($row['revoked_at']),
            null === $row['revoked_at'] && (null === $row['expires_at'] || new \DateTimeImmutable($row['expires_at']) > $now),
        ), $rows);
    }
}
