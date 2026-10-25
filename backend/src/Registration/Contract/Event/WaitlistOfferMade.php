<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\WaitlistEntryId;
use Eventor\Shared\Domain\Id\WaitlistOfferId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A freed seat was offered to the first waiting entry (REG-12).
 *
 * P1. Planned subscribers: communication.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.waitlist_offer_made', version: 1)]
#[PendingSubscribers('communication')]
final readonly class WaitlistOfferMade
{
    public function __construct(
        public OrganizerId $organizerId,
        public WaitlistOfferId $offerId,
        public WaitlistEntryId $entryId,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
