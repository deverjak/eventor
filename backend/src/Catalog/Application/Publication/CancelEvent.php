<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publication;

use Eventor\Shared\Domain\Id\EventId;

final readonly class CancelEvent
{
    public function __construct(
        public EventId $eventId,
        public string $reason,
    ) {}
}
