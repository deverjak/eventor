<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Event;

/**
 * Aggregate side of domain events: `$this->record(new OrderWasPlaced(…))`. The property is not mapped by
 * Doctrine, so use this trait together with `implements RecordsEventsInterface`.
 */
trait RecordsEvents
{
    /** @var list<object> */
    private array $recordedEvents = [];

    protected function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return list<object>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
