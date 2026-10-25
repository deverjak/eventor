<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\WaitlistEntryId;
use Eventor\Shared\Domain\Id\WaitlistOfferId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A waitlist offer timed out; the seat goes to the next entry.
 *
 * P1. Planned subscribers: communication.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.waitlist_offer_expired', version: 1)]
#[PendingSubscribers('communication')]
final readonly class WaitlistOfferExpired
{
    public function __construct(
        public OrganizerId $organizerId,
        public WaitlistOfferId $offerId,
        public WaitlistEntryId $entryId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
