<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Event;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: the operator cannot settle a situation at the desk (CHK-09). Published as `checkin.escalation_raised`.
 */
final readonly class EscalationWasRaised
{
    public function __construct(
        public OrganizerId $organizerId,
        public CheckInListId $listId,
        public ParticipationId $participationId,
        public CheckInSituation $situation,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
