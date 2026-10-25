<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Team;

use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class RemoveMember
{
    public function __construct(
        public OrganizerId $organizerId,
        public MembershipId $membershipId,
    ) {}
}
