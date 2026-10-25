<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Messaging;

/**
 * An integration event without subscribers is a build error unless it is explicitly declared as published
 * for future/external consumers.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class NoSubscribers
{
    public function __construct(public string $reason = '') {}
}
