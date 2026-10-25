<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Organizer;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'contact_email_required', status: 422)]
final class ContactEmailRequired extends DomainException
{
    public static function create(): self
    {
        return new self('Fill in the contact e-mail of the organizer before connecting the bank account.');
    }
}
