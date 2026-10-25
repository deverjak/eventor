<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Registration\Contract\AttentionCause;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A participation needs a human decision (payment after expiry or after cancellation).
 *
 * P0. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.participation_requires_attention', version: 1)]
#[PendingSubscribers('attention')]
final readonly class ParticipationRequiresAttention
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $participationId,
        public AttentionCause $cause,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
