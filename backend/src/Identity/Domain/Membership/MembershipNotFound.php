<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Membership;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\MembershipId;

#[AsProblem(code: 'membership_not_found', status: 404)]
final class MembershipNotFound extends DomainException
{
    public static function forId(MembershipId $id): self
    {
        return new self(\sprintf('Team member "%s" was not found.', $id->toString()));
    }
}
