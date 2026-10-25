<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Eventor\Catalog\Domain\Exception\ActivityNotFound;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;

interface ActivityRepository
{
    /**
     * @throws ActivityNotFound when the activity does not exist in this event
     */
    public function get(EventId $event, ActivityId $id): Activity;

    /**
     * @return list<Activity> ordered by position
     */
    public function forEvent(EventId $event): array;

    public function nextPosition(EventId $event): int;

    public function add(Activity $activity): void;

    public function remove(Activity $activity): void;
}
