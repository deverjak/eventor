<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Registration\Contract\CapacityReleaseCause;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * Seats became free in the buckets (cancel, expiry, raised limit, expired waitlist offer). Drives the waitlist (P1) and the availability cache.
 *
 * P0. Planned subscribers: registration, catalog.
 * Note: `registration.offer_seat_to_waitlist` is P1.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.capacity_released', version: 1)]
#[PendingSubscribers('registration', 'catalog')]
final readonly class CapacityReleased
{
    /**
     * @param list<CapacityBucketId> $bucketIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public array $bucketIds,
        public int $seats,
        public CapacityReleaseCause $cause,
        public \DateTimeImmutable $occurredAt,
    ) {
        if ([] === $bucketIds) {
            throw new \InvalidArgumentException('At least one bucket was released.');
        }
        if ($seats < 1) {
            throw new \InvalidArgumentException('At least one seat must be released.');
        }
    }
}
