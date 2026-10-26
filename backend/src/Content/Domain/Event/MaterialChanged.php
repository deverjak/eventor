<?php

declare(strict_types=1);

namespace Eventor\Content\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\MaterialId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * A material was added, published, unpublished or (un)designated for the archive.
 */
final readonly class MaterialChanged implements Auditable
{
    /**
     * @param array<string, scalar|null> $after
     */
    public function __construct(
        public OrganizerId $organizerId,
        public MaterialId $materialId,
        public string $action,
        public array $after,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('material', $this->materialId->toString(), 'content.material_'.$this->action, $this->organizerId, after: $this->after);
    }
}
