<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Invitation;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'invitation_email_mismatch', status: 403)]
final class InvitationEmailMismatch extends DomainException
{
    public static function create(): self
    {
        return new self('This invitation was sent to another e-mail address. Sign in with the invited account.');
    }
}
