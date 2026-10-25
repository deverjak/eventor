<?php

declare(strict_types=1);

namespace Eventor\Audit\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Eventor\Shared\Application\Audit\AuditRecord;
use Eventor\Shared\Application\Audit\AuditRecorder;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;

/**
 * Appends to `audit.audit_entry` on the connection (and therefore in the transaction) of the command being
 * handled. INSERT only: the application role cannot UPDATE or DELETE audit rows.
 */
#[AsAlias(AuditRecorder::class)]
final readonly class DbalAuditRecorder implements AuditRecorder
{
    public function __construct(private Connection $connection) {}

    public function record(AuditRecord $record): void
    {
        $change = $record->change;

        $this->connection->executeStatement(
            'INSERT INTO audit.audit_entry
                (id, organizer_id, actor_type, actor_id, actor_label, entity_type, entity_id, action, before, after, reason, correlation_id, occurred_at)
             VALUES
                (:id, :organizer, :actor_type, :actor_id, :actor_label, :entity_type, :entity_id, :action,
                 CAST(:before AS jsonb), CAST(:after AS jsonb), :reason, :correlation, :occurred_at)',
            [
                'id' => Uuid::v7()->toRfc4122(),
                'organizer' => $record->organizerId?->toString(),
                'actor_type' => $record->actor->type->value,
                'actor_id' => $record->actor->id,
                'actor_label' => $record->actor->label,
                'entity_type' => $change->entityType,
                'entity_id' => $change->entityId,
                'action' => $change->action,
                'before' => null === $change->before ? null : json_encode($change->before, \JSON_THROW_ON_ERROR),
                'after' => null === $change->after ? null : json_encode($change->after, \JSON_THROW_ON_ERROR),
                'reason' => $change->reason,
                'correlation' => $record->correlationId,
                'occurred_at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
            ],
        );
    }
}
