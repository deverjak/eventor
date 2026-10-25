<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Command;

use Eventor\Shared\Domain\Messaging\ReadOnlyCommand;

#[ReadOnlyCommand]
final readonly class ReadsOnly {}
