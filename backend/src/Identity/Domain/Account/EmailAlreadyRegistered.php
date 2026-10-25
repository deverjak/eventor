<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Account;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'email_already_registered', status: 409)]
final class EmailAlreadyRegistered extends DomainException
{
    public static function create(): self
    {
        return new self('An account with this e-mail already exists. Sign in instead.');
    }
}
