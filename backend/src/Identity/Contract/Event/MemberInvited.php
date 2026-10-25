<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract\Event;

use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A team invitation was created (ORG-03). The e-mail address and the accept link are read with `InvitationQuery::forDelivery()`. `role` is the role code (`OWNER`, `EVENT_MANAGER`, `FINANCE`, `CHECK_IN`).
 *
 * P0. Planned subscribers: communication.
 * Note: Catalogue payload lists `organizerId` as a field; it is the envelope field here.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'identity.member_invited', version: 1)]
#[PendingSubscribers('communication')]
final readonly class MemberInvited
{
    public function __construct(
        public OrganizerId $organizerId,
        public InvitationId $invitationId,
        public string $role,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
