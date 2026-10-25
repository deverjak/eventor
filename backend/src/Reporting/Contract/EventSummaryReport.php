<?php

declare(strict_types=1);

namespace Eventor\Reporting\Contract;

use Eventor\Shared\Domain\Id\EventId;

/**
 * One-page summary of an event, live (no cache) so it can serve the check-in dashboard and the report after the event.
 */
final readonly class EventSummaryReport
{
    public function __construct(
        public EventId $eventId,
        public ParticipationCounts $participations,
        public FinanceTotals $finance,
        public int $checkedIn,
        public int $deliveriesSent,
        public int $openAttentionItems,
    ) {}
}
