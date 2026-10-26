<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Desk;

use Eventor\CheckIn\Contract\Exception\CheckInNotFound;
use Eventor\CheckIn\Domain\CheckInListRepository;
use Eventor\CheckIn\Domain\CheckInRecord;
use Eventor\CheckIn\Domain\CheckInRecordRepository;
use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * A check-in named by a route `/events/{eventId}/check-ins/{checkInId}`, locked for update: a row of another event
 * (or organizer) is "not found", which keeps event-scoped members inside their events.
 */
final readonly class EventCheckIns
{
    public function __construct(
        private CheckInRecordRepository $records,
        private CheckInListRepository $lists,
    ) {}

    /**
     * @throws CheckInNotFound
     */
    public function lock(string $eventId, string $checkInId): CheckInRecord
    {
        $id = CheckInId::fromString($checkInId);
        $record = $this->records->lock($id);
        $list = null === $record ? null : $this->lists->find($record->listId());
        if (null === $record || null === $list || !$list->eventId()->equals(EventId::fromString($eventId))) {
            throw CheckInNotFound::forId($id);
        }

        return $record;
    }
}
