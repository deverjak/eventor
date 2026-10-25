<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Command;

/** Command that records a domain event (it registers an organizer). */
final readonly class EmitsEvent
{
    public function __construct(public string $slug) {}
}
