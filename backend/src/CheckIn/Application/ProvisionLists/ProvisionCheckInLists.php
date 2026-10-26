<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\ProvisionLists;

/**
 * `catalog.event_published` -> one check-in list per activity (CHK-01).
 */
final readonly class ProvisionCheckInLists
{
    /**
     * @param list<string> $activityIds
     */
    public function __construct(
        public string $eventId,
        public array $activityIds,
    ) {}
}
