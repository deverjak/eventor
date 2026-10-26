<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Event;

use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: a participant arrived (CHK-05). Published as `checkin.participation_checked_in`.
 */
final readonly class CheckedIn
{
    public function __construct(
        public OrganizerId $organizerId,
        public CheckInId $checkInId,
        public ParticipationId $participationId,
        public CheckInListId $listId,
        public ?CheckInStationId $stationId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
