<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A reservation timed out (CAP-04). The payment request in Pairrot is deliberately not cancelled, so a late payment still matches (S05/S06).
 *
 * P0. Planned subscribers: communication, reporting.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.participation_expired', version: 1)]
#[PendingSubscribers('communication', 'reporting')]
final readonly class ParticipationExpired
{
    /**
     * @param list<CapacityBucketId> $bucketIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $participationId,
        public array $bucketIds,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
