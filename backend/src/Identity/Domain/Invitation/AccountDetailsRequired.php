<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Invitation;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'account_details_required', status: 422)]
final class AccountDetailsRequired extends DomainException
{
    public static function create(): self
    {
        return new self('Choose a password and a display name to create your account and accept the invitation.');
    }
}
