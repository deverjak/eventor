<?php

declare(strict_types=1);

namespace Eventor\Reporting\Contract;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Dashboards and the report after the event (ADM-01, ADM-08). Backed by read-only views in the `reporting` schema, so
 * the numbers are derived, never a second source of truth. Counts only: no personal data.
 */
interface ReportQuery
{
    public function eventSummary(OrganizerId $organizer, EventId $event): EventSummaryReport;
}
