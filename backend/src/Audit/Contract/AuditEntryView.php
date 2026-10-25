<?php

declare(strict_types=1);

namespace Eventor\Audit\Contract;

/**
 * One line of an entity's history as other modules and the API see it.
 */
final readonly class AuditEntryView
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
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
}
