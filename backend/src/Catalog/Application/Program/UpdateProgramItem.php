<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\ProgramItemId;

final readonly class UpdateProgramItem
{
    public function __construct(
        public EventId $eventId,
        public ProgramItemId $programItemId,
        public ProgramItemInput $input,
    ) {}
}
