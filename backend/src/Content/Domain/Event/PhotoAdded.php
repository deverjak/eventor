<?php

declare(strict_types=1);

namespace Eventor\Content\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PhotoId;

/**
 * A photo was stored in a gallery; becomes `content.photo_uploaded`, whose subscriber creates the thumbnail.
 */
final readonly class PhotoAdded implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public PhotoId $photoId,
        public CollectionId $collectionId,
        public string $storageKey,
        public \DateTimeImmutable $occurredAt,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('photo', $this->photoId->toString(), 'content.photo_added', $this->organizerId, after: ['collectionId' => $this->collectionId->toString()]);
    }
}
