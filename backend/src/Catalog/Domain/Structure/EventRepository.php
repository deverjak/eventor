<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Shared\Domain\Id\EventId;

/**
 * Events of the active tenant (the Doctrine `organizer` filter scopes every lookup).
 */
interface EventRepository
{
    /**
     * @throws EventNotFound when the event does not exist for the current organizer
     */
    public function get(EventId $id): Event;

    public function slugExists(string $slug): bool;

    public function add(Event $event): void;
}
