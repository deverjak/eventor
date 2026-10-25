<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Event;

/**
 * Implemented by aggregates that record domain events (use the RecordsEvents trait). DomainEventsMiddleware
 * collects the recorded events from the unit of work after the handler returns.
 */
interface RecordsEventsInterface
{
    /**
     * Returns the recorded events and clears them.
     *
     * @return list<object>
     */
    public function releaseEvents(): array;
}
