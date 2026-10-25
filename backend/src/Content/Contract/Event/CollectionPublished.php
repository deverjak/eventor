<?php

declare(strict_types=1);

namespace Eventor\Content\Contract\Event;

use Eventor\Content\Contract\CollectionVisibility;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A gallery or external collection became visible (POST-02). Attention warns about missing likeness consents.
 *
 * P1. Planned subscribers: attention.
 * Note: `attention.warn_missing_likeness_consents` (POST-06).
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'content.collection_published', version: 1)]
#[PendingSubscribers('attention')]
final readonly class CollectionPublished
{
    public function __construct(
        public OrganizerId $organizerId,
        public CollectionId $collectionId,
        public EventId $eventId,
        public CollectionVisibility $visibility,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
