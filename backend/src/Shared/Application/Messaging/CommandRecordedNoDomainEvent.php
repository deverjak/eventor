<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Messaging;

/**
 * A command finished without recording a single domain event. ADR 001 says every command emits at least one
 * (it is how audit, integration events and the "nothing changes silently" rule work), unless it is declared
 * #[ReadOnlyCommand] or its handler carries #[NoDomainEvent('reason')]. Enforced in dev and test, not in prod.
 */
final class CommandRecordedNoDomainEvent extends \LogicException
{
    public static function for(string $commandClass): self
    {
        return new self(\sprintf(
            '%s recorded no domain event. Record one in the aggregate, or mark the command #[ReadOnlyCommand], or its handler #[NoDomainEvent(reason)].',
            $commandClass,
        ));
    }
}
