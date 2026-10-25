<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Registration\Contract\RestoredBy;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * An expired or flagged participation was confirmed again.
 *
 * P0. Planned subscribers: communication, attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.participation_restored', version: 1)]
#[PendingSubscribers('communication')]
final readonly class ParticipationRestored
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $participationId,
        public RestoredBy $by,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
