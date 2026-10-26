<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query\View;

use Eventor\Shared\Domain\Id\EventId;

/**
 * The operational overview of an event: per activity and in total (CHK-10). The full dashboard belongs to Reporting.
 */
final readonly class OverviewView
{
    /**
     * @param list<OverviewRowView> $lists
     */
    public function __construct(
        public EventId $eventId,
        public array $lists,
        public int $expected,
        public int $checkedIn,
        public int $missing,
        public int $unpaid,
        public int $cashReceivedMinor,
    ) {}
}
