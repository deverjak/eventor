<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract;

use Eventor\Shared\Domain\Id\CheckInListId;

/**
 * Live counters of a list: expected = confirmed participations of the activity.
 */
final readonly class CheckInCounts
{
    public function __construct(
        public CheckInListId $listId,
        public int $expected,
        public int $checkedIn,
    ) {}
}
