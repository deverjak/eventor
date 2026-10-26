<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Port;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Content belongs to a published (or later) event of the organizer: a draft has no gallery yet.
 */
final readonly class EventGuard
{
    public function __construct(private EventCatalog $events) {}

    /**
     * @throws EventNotFound
     */
    public function assertExists(OrganizerId $organizer, EventId $event): void
    {
        $this->events->summary($organizer, $event);
    }
}
