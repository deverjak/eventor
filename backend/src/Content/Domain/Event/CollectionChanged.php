<?php

declare(strict_types=1);

namespace Eventor\Content\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * A collection was created, published, unpublished or (un)designated for the archive. `published` becomes the
 * integration event `content.collection_published`.
 */
final readonly class CollectionChanged implements Auditable
{
    /**
     * @param array<string, scalar|null> $after
     */
    public function __construct(
        public OrganizerId $organizerId,
        public CollectionId $collectionId,
        public EventId $eventId,
        public string $action,
        public array $after,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('collection', $this->collectionId->toString(), 'content.collection_'.$this->action, $this->organizerId, after: $this->after);
    }
}
