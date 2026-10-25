<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Event;

use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A participant was checked in (CHK-05). There is at most one valid check-in per participation.
 *
 * P0. Planned subscribers: reporting.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'checkin.participation_checked_in', version: 1)]
#[PendingSubscribers('reporting')]
final readonly class ParticipationCheckedIn
{
    public function __construct(
        public OrganizerId $organizerId,
        public CheckInId $checkInId,
        public ParticipationId $participationId,
        public CheckInListId $listId,
        public ?CheckInStationId $stationId,
        public \DateTimeImmutable $checkedInAt,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
