<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a news post was created, edited or published (PRG-04). `catalog.news_published` follows a publication.
 */
final readonly class NewsPostModified implements Auditable
{
    /**
     * @param 'created'|'updated'|'published' $action
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public NewsPostId $newsPostId,
        public string $action,
        public bool $broadcast = false,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('news_post', $this->newsPostId->toString(), 'news_post.'.$this->action, $this->organizerId, after: ['eventId' => $this->eventId->toString(), 'broadcast' => $this->broadcast]);
    }
}
