<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * Money arrived for expired participations (CAP-05, S05). `autoRestore` = the activity restores them automatically when capacity allows (S06).
 *
 * P0. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.late_payment_received', version: 1)]
#[PendingSubscribers('attention')]
final readonly class LatePaymentReceived
{
    /**
     * @param list<ParticipationId> $participationIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public array $participationIds,
        public bool $capacityAvailable,
        public bool $autoRestore,
        public \DateTimeImmutable $occurredAt,
    ) {
        if ([] === $participationIds) {
            throw new \InvalidArgumentException('At least one participation is affected.');
        }
    }
}
