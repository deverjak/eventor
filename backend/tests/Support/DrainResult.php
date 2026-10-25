<?php

declare(strict_types=1);

namespace Eventor\Tests\Support;

/**
 * What QueueDrainer::drain() did: handler runs that succeeded, and handler runs that threw (retries count each time).
 */
final readonly class DrainResult
{
    public function __construct(
        public int $handled,
        public int $failed,
    ) {}
}
