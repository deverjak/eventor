<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Shared\Domain\Id\EventId;

final readonly class CreateNewsPost
{
    public function __construct(
        public EventId $eventId,
        public NewsPostInput $input,
    ) {}
}
