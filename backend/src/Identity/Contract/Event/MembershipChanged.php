<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract\Event;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\NoSubscribers;

/**
 * A team membership was granted, changed or revoked. Carries ids only (no e-mail, no name).
 * No module subscribes yet (Communication will send invitations, Audit may mirror it), hence #[NoSubscribers].
 */
#[IntegrationEvent(name: 'identity.membership_changed', version: 1)]
#[NoSubscribers(reason: 'Subscribers arrive with ORG-03 (team invitations).')]
final readonly class MembershipChanged
{
    public function __construct(
        public string $organizerId,
        public string $membershipId,
        public string $userAccountId,
        public string $role,
        public string $change,
    ) {}
}
