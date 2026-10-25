<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\ProgramItemId;

final readonly class RemoveProgramItem
{
    public function __construct(
        public EventId $eventId,
        public ProgramItemId $programItemId,
        public bool $important = false,
    ) {}
}
