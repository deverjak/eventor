<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publication;

use Eventor\Shared\Domain\Id\EventId;

final readonly class CompleteEvent
{
    public function __construct(public EventId $eventId) {}
}
