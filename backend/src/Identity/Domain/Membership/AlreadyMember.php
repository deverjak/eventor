<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Membership;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'already_member', status: 409)]
final class AlreadyMember extends DomainException
{
    public static function create(): self
    {
        return new self('This person is already a member of the organizer.');
    }
}
