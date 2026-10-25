<?php

declare(strict_types=1);

namespace Eventor\Audit\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Audit\Application\Query\View\AuditLogEntryView;
use Eventor\Audit\Application\Query\View\AuditLogPage;
use Eventor\Audit\Contract\AuditActorKind;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The audit log of an organizer as pages, newest first (AUD-04). The table is append-only; this is its only reader
 * besides the per-entity trail.
 */
final readonly class AuditLogQuery
{
    public function __construct(private Connection $connection) {}

    public function page(OrganizerId $organizer, ?string $entityType, ?string $entityId, ?AuditActorKind $actor, int $page, int $perPage): AuditLogPage
    {
        $where = 'organizer_id = :organizer';
        $params = ['organizer' => $organizer->toString()];
        if (null !== $entityType) {
            $where .= ' AND entity_type = :type';
            $params['type'] = $entityType;
        }
        if (null !== $entityId) {
            $where .= ' AND entity_id = :id';
            $params['id'] = $entityId;
        }
        if (null !== $actor) {
            $where .= ' AND actor_type = :actor';
            $params['actor'] = $actor->storedValue();
        }

        $total = self::count($this->connection->fetchOne("SELECT count(*) FROM audit.audit_entry WHERE $where", $params));
        /** @var list<array<string, string|null>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, entity_type, entity_id, action, actor_type, actor_id, actor_label, before, after, reason, correlation_id, occurred_at
               FROM audit.audit_entry WHERE $where ORDER BY occurred_at DESC, id DESC LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        $items = [];
        foreach ($rows as $row) {
            $view = AuditTrailQuery::view($row);
            $items[] = new AuditLogEntryView(
                (string) $row['id'],
                (string) $row['entity_type'],
                (string) $row['entity_id'],
                $view->action,
                $view->actorType,
                $view->actorId,
                $view->actorLabel,
                $view->before,
                $view->after,
                $view->reason,
                $view->correlationId,
                $view->occurredAt,
            );
        }

        return new AuditLogPage($items, $total, $page, $perPage);
    }

    private static function count(mixed $value): int
    {
        return \is_int($value) || \is_string($value) ? (int) $value : 0;
    }
}
