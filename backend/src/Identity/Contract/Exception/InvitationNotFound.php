<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\InvitationId;

/**
 * The invitation does not exist, expired or belongs to another organizer.
 */
#[AsProblem(code: 'invitation_not_found', status: 404)]
final class InvitationNotFound extends DomainException
{
    public static function forId(InvitationId $id): self
    {
        return new self(\sprintf('Invitation "%s" was not found.', $id->toString()));
    }
}
