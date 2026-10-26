<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query\View;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInListId;

/**
 * One activity in the operational overview (CHK-10). `expected` = confirmed participations, `missing` = expected and not
 * arrived, `unpaid` = active participations (confirmed, pending, requiring attention) whose order is not paid,
 * `cashReceivedMinor` = cash taken at this list's desk.
 */
final readonly class OverviewRowView
{
    public function __construct(
        public CheckInListId $listId,
        public ActivityId $activityId,
        public string $name,
        public ?\DateTimeImmutable $opensAt,
        public int $expected,
        public int $checkedIn,
        public int $missing,
        public int $unpaid,
        public int $cashReceivedMinor,
    ) {}
}
