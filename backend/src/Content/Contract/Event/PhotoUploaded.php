<?php

declare(strict_types=1);

namespace Eventor\Content\Contract\Event;

use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PhotoId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;

/**
 * The upload of a photo was confirmed (POST-01); thumbnails are generated asynchronously.
 *
 * P0. Planned subscribers: content.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'content.photo_uploaded', version: 1)]
final readonly class PhotoUploaded
{
    public function __construct(
        public OrganizerId $organizerId,
        public PhotoId $photoId,
        public CollectionId $collectionId,
        public string $storageKey,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
