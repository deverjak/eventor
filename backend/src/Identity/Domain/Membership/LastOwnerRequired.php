<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Membership;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'last_owner_required', status: 409)]
final class LastOwnerRequired extends DomainException
{
    public static function create(): self
    {
        return new self('An organizer needs at least one owner. Promote another member first.');
    }
}
