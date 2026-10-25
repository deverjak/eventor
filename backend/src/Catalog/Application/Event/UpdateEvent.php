<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Event;

use Eventor\Shared\Domain\Id\EventId;

final readonly class UpdateEvent
{
    public function __construct(
        public EventId $eventId,
        public EventDetailsInput $details,
        public ?int $expectedVersion = null,
    ) {}
}
