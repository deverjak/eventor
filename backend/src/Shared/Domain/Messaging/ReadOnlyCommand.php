<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Messaging;

/**
 * On a command message class: the command changes no state (it exists only for the bus middleware), so it needs
 * no domain event.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class ReadOnlyCommand {}
