<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract;

use Eventor\CheckIn\Contract\Exception\CheckInListNotFound;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Operational read model of check-ins (CHK-10). The desk itself (scan, search, check in) is CheckIn's own HTTP API;
 * this is for modules that need to know who arrived.
 */
interface CheckInQuery
{
    /**
     * @return list<CheckInListView> the check-in lists of an event (default: one per activity)
     */
    public function lists(OrganizerId $organizer, EventId $event): array;

    /**
     * The valid (not voided) check-in of a participation, if any.
     */
    public function recordFor(OrganizerId $organizer, ParticipationId $participation): ?CheckInRecordView;

    /**
     * Narrows participations to those with a valid check-in (segments "arrived / not arrived").
     *
     * @param list<ParticipationId> $participations
     *
     * @return list<ParticipationId>
     */
    public function checkedIn(OrganizerId $organizer, array $participations): array;

    /**
     * @throws CheckInListNotFound
     */
    public function counts(OrganizerId $organizer, CheckInListId $list): CheckInCounts;
}
