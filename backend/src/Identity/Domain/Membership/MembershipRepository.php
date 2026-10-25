<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Membership;

interface MembershipRepository
{
    public function add(Membership $membership): void;
}
