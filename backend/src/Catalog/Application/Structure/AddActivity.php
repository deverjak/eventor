<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Shared\Domain\Id\EventId;

final readonly class AddActivity
{
    public function __construct(
        public EventId $eventId,
        public ActivityInput $input,
    ) {}
}
