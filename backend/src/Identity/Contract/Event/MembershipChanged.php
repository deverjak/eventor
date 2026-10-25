<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract\Event;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A membership was granted (previousRole null), changed or revoked (newRole null). `eventScope` empty = all events of the organizer. Role codes: OWNER, EVENT_MANAGER, FINANCE, CHECK_IN.
 *
 * P0. Planned subscribers: identity.
 * Note: `userAccountId` is an addition to the catalogue payload (id only), kept from the W0 event.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'identity.membership_changed', version: 1)]
#[PendingSubscribers('identity')]
final readonly class MembershipChanged
{
    /**
     * @param list<EventId> $eventScope
     */
    public function __construct(
        public OrganizerId $organizerId,
        public MembershipId $membershipId,
        public UserAccountId $userAccountId,
        public ?string $previousRole,
        public ?string $newRole,
        public array $eventScope,
        public \DateTimeImmutable $occurredAt,
    ) {
        if (null === $previousRole && null === $newRole) {
            throw new \InvalidArgumentException('A membership change has a previous or a new role.');
        }
    }
}
