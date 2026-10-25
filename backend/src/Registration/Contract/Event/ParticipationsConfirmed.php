<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Registration\Contract\ConfirmationReason;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * Participations became confirmed (paid, cash on site, free, manual, restored).
 *
 * P0. Planned subscribers: communication, checkin, registration.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.participations_confirmed', version: 1)]
#[PendingSubscribers('communication', 'checkin', 'registration')]
final readonly class ParticipationsConfirmed
{
    /**
     * @param list<ParticipationId> $participationIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public array $participationIds,
        public ConfirmationReason $reason,
        public \DateTimeImmutable $occurredAt,
    ) {
        if ([] === $participationIds) {
            throw new \InvalidArgumentException('At least one participation must be confirmed.');
        }
    }
}
