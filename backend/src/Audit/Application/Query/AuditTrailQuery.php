<?php

declare(strict_types=1);

namespace Eventor\Audit\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Audit\Contract\AuditEntryView;
use Eventor\Audit\Contract\AuditTrail;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AuditTrail::class)]
final readonly class AuditTrailQuery implements AuditTrail
{
    public function __construct(private Connection $connection) {}

    public function forEntity(OrganizerId $organizer, string $entityType, string $entityId): array
    {
        /** @var list<array{action: string, actor_type: string, actor_id: string|null, actor_label: string|null, before: string|null, after: string|null, reason: string|null, correlation_id: string, occurred_at: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT action, actor_type, actor_id, actor_label, before, after, reason, correlation_id, occurred_at
               FROM audit.audit_entry
              WHERE organizer_id = :organizer AND entity_type = :type AND entity_id = :id
              ORDER BY occurred_at, id',
            ['organizer' => $organizer->toString(), 'type' => $entityType, 'id' => $entityId],
        );

        return array_map(static fn(array $row): AuditEntryView => new AuditEntryView(
            $row['action'],
            $row['actor_type'],
            $row['actor_id'],
            $row['actor_label'],
            self::decode($row['before']),
            self::decode($row['after']),
            $row['reason'],
            $row['correlation_id'],
            new \DateTimeImmutable($row['occurred_at']),
        ), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decode(?string $json): ?array
    {
        if (null === $json) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
