<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Event;

use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A check-in was voided (CHK-07); `replacedBy` is set when the correction created a new valid record.
 *
 * P0. Planned subscribers: reporting.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'checkin.check_in_voided', version: 1)]
#[PendingSubscribers('reporting')]
final readonly class CheckInVoided
{
    public function __construct(
        public OrganizerId $organizerId,
        public CheckInId $checkInId,
        public ParticipationId $participationId,
        public ?CheckInId $replacedBy,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
