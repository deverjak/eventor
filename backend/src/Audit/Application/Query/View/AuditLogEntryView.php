<?php

declare(strict_types=1);

namespace Eventor\Audit\Application\Query\View;

/**
 * One line of the audit log with the entity it is about (the Contract's `AuditEntryView` is already scoped to one entity).
 */
final readonly class AuditLogEntryView
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public string $id,
        public string $entityType,
        public string $entityId,
        public string $action,
        public string $actorType,
        public ?string $actorId,
        public ?string $actorLabel,
        public ?array $before,
        public ?array $after,
        public ?string $reason,
        public string $correlationId,
        public \DateTimeImmutable $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'action' => $this->action,
            'actor' => ['type' => $this->actorType, 'id' => $this->actorId, 'label' => $this->actorLabel],
            'before' => $this->before,
            'after' => $this->after,
            'reason' => $this->reason,
            'correlationId' => $this->correlationId,
            'occurredAt' => $this->occurredAt->format(\DATE_ATOM),
        ];
    }
}
