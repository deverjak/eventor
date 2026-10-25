<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Membership;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'invalid_event_scope', status: 422)]
final class InvalidEventScope extends DomainException
{
    public static function notAllowedFor(Role $role): self
    {
        return new self(\sprintf('The role %s always covers all events; an event scope is possible only for EVENT_MANAGER and CHECK_IN.', $role->value));
    }
}
