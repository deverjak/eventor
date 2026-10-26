<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A participation moved to another person (REG-10): a new participation took over the capacity claim.
 *
 * P1. Planned subscribers: communication, legal.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.participation_transferred', version: 1)]
#[PendingSubscribers('legal')]
final readonly class ParticipationTransferred
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $previousParticipationId,
        public ParticipationId $newParticipationId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
