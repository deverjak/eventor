<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Registration\Contract\CancellationReason;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: a participation was cancelled by the participant, the organizer or the system (REG-09, REG-14,
 * event cancelled). Published as `registration.participation_cancelled`.
 */
final readonly class ParticipationWasCancelled
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $participationId,
        public CancelledBy $by,
        public CancellationReason $reason,
    ) {}
}
