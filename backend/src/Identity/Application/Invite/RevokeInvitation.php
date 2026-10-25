<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Invite;

use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class RevokeInvitation
{
    public function __construct(
        public OrganizerId $organizerId,
        public InvitationId $invitationId,
    ) {}
}
