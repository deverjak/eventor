<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * An integration (Pairrot) is unavailable or paused (circuit breaker, paused inbound address). `organizerId` is null for a platform-wide outage.
 *
 * P0. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.integration_degraded', version: 1)]
#[PendingSubscribers('attention')]
final readonly class IntegrationDegraded
{
    public function __construct(
        public ?OrganizerId $organizerId,
        public string $integration,
        public string $reason,
        public \DateTimeImmutable $since,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
