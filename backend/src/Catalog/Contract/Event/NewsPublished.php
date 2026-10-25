<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Event;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A news post was published (PUB-04). `broadcast` = also send it by e-mail to the participants.
 *
 * P0. Planned subscribers: communication.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'catalog.news_published', version: 1)]
#[PendingSubscribers('communication')]
final readonly class NewsPublished
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public NewsPostId $newsPostId,
        public bool $broadcast,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
