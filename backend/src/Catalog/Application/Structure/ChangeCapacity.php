<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Shared\Domain\Id\EventId;

final readonly class ChangeCapacity
{
    public function __construct(
        public EventId $eventId,
        public ChangeCapacityInput $input,
    ) {}
}
