<?php

declare(strict_types=1);

namespace Eventor\Audit\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Audit\Contract\AuditActorKind;
use Eventor\Audit\Contract\AuditEntryView;
use Eventor\Audit\Contract\AuditTrail;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AuditTrail::class)]
final readonly class AuditTrailQuery implements AuditTrail
{
    public function __construct(private Connection $connection) {}

    public function forEntity(OrganizerId $organizer, string $entityType, string $entityId, ?AuditActorKind $actor = null): array
    {
        $sql = 'SELECT action, actor_type, actor_id, actor_label, before, after, reason, correlation_id, occurred_at
                  FROM audit.audit_entry
                 WHERE organizer_id = :organizer AND entity_type = :type AND entity_id = :id';
        $params = ['organizer' => $organizer->toString(), 'type' => $entityType, 'id' => $entityId];
        if (null !== $actor) {
            $sql .= ' AND actor_type = :actor';
            $params['actor'] = $actor->storedValue();
        }

        /** @var list<array<string, string|null>> $rows */
        $rows = $this->connection->fetchAllAssociative($sql.' ORDER BY occurred_at, id', $params);

        return array_map(static fn(array $row): AuditEntryView => self::view($row), $rows);
    }

    /**
     * @param array<string, string|null> $row
     */
    public static function view(array $row): AuditEntryView
    {
        return new AuditEntryView(
            (string) $row['action'],
            (string) $row['actor_type'],
            $row['actor_id'],
            $row['actor_label'],
            self::decode($row['before']),
            self::decode($row['after']),
            $row['reason'],
            (string) $row['correlation_id'],
            new \DateTimeImmutable((string) $row['occurred_at']),
        );
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
