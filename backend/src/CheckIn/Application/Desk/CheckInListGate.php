<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Desk;

use Eventor\CheckIn\Contract\Exception\CheckInListClosed;
use Eventor\CheckIn\Contract\Exception\CheckInListNotFound;
use Eventor\CheckIn\Domain\CheckInList;
use Eventor\CheckIn\Domain\CheckInListRepository;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * The list a desk request names, checked against the event of the URL: a list of another event (or organizer) is
 * "not found", a list of a cancelled event is closed.
 */
final readonly class CheckInListGate
{
    public function __construct(private CheckInListRepository $lists) {}

    /**
     * @throws CheckInListNotFound
     * @throws CheckInListClosed
     */
    public function open(string $eventId, string $listId): CheckInList
    {
        $id = CheckInListId::fromString($listId);
        $list = $this->lists->find($id);
        if (null === $list || !$list->eventId()->equals(EventId::fromString($eventId))) {
            throw CheckInListNotFound::forId($id);
        }
        if (!$list->isOpen()) {
            throw CheckInListClosed::forId($id);
        }

        return $list;
    }
}
