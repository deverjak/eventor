<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Command;

/** Command whose handler declares #[NoDomainEvent] with a reason. */
final readonly class ExemptByReason {}
