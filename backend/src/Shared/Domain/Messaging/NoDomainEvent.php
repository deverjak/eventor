<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Messaging;

/**
 * On a command handler: this command intentionally records no domain event. The reason is mandatory and is
 * read by CommandEmitsEventTest (ADR 001: every command emits an event or is #[ReadOnlyCommand]).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class NoDomainEvent
{
    public function __construct(public string $reason) {}
}
