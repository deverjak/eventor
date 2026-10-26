<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\CancelLists;

/**
 * `catalog.event_cancelled` -> the lists of the event stop admitting people.
 */
final readonly class CancelCheckInLists
{
    public function __construct(public string $eventId) {}
}
