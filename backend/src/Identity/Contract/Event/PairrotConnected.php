<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract\Event;

use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * The organizer finished the Pairrot onboarding (ORG-02): transfer payments may be enabled. `subtenantId` and `bankConnectionId` are Pairrot identifiers.
 *
 * P0. Planned subscribers: catalog.
 * Note: Catalogue payload lists `organizerId` as a field; it is the envelope field here.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'identity.pairrot_connected', version: 1)]
#[PendingSubscribers('catalog')]
final readonly class PairrotConnected
{
    public function __construct(
        public OrganizerId $organizerId,
        public string $subtenantId,
        public string $bankConnectionId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
