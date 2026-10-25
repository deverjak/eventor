<?php

declare(strict_types=1);

namespace Eventor\Audit\Domain;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * One immutable audit record (AUD-01..03). The table is append-only for the application role: `eventor_app` has
 * no UPDATE/DELETE/TRUNCATE on it (SchemaGrantsTest). Rows are written with SQL by `DbalAuditRecorder` in the
 * transaction of the change; this mapping exists for schema validation and read models.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'audit_entry', schema: 'audit')]
#[ORM\Index(name: 'audit_entry_entity_idx', columns: ['organizer_id', 'entity_type', 'entity_id', 'occurred_at'])]
class AuditEntry
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'guid')]
        private string $id,
        #[ORM\Column(type: 'organizer_id', nullable: true)]
        private ?OrganizerId $organizerId,
        #[ORM\Column(type: 'string', length: 32)]
        private string $actorType,
        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private ?string $actorId,
        #[ORM\Column(type: 'string', length: 128, nullable: true)]
        private ?string $actorLabel,
        #[ORM\Column(type: 'string', length: 64)]
        private string $entityType,
        #[ORM\Column(type: 'string', length: 64)]
        private string $entityId,
        #[ORM\Column(type: 'string', length: 96)]
        private string $action,
        #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
        private ?array $before,
        #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
        private ?array $after,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $reason,
        #[ORM\Column(type: 'string', length: 64)]
        private string $correlationId,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function organizerId(): ?OrganizerId
    {
        return $this->organizerId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): string
    {
        return $this->entityId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
