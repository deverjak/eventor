<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Audit;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * One audited change as the domain describes it. `before`/`after` hold neutral fields only: personal data
 * belongs into audit_pii (chapter 08, "Audit bez osobních údajů").
 */
final readonly class AuditChange
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public string $entityType,
        public string $entityId,
        public string $action,
        public ?OrganizerId $organizerId = null,
        public ?array $before = null,
        public ?array $after = null,
        public ?string $reason = null,
    ) {}
}
