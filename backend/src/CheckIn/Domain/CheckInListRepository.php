<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;

interface CheckInListRepository
{
    public function add(CheckInList $list): void;

    public function find(CheckInListId $id): ?CheckInList;

    public function findForActivity(ActivityId $activity): ?CheckInList;

    /**
     * @return list<CheckInList>
     */
    public function forEvent(EventId $event): array;
}
