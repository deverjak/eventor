<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Event;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * The operator cannot resolve the situation at the desk (CHK-09) and passes it on.
 *
 * P0. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'checkin.escalation_raised', version: 1)]
#[PendingSubscribers('attention')]
final readonly class EscalationRaised
{
    public function __construct(
        public OrganizerId $organizerId,
        public ParticipationId $participationId,
        public CheckInListId $listId,
        public CheckInSituation $situation,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
