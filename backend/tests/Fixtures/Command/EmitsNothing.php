<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Command;

/** Command that records no domain event and is not exempt: ADR 001 says that is a bug. */
final readonly class EmitsNothing {}
