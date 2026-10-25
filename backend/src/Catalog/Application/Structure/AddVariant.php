<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;

final readonly class AddVariant
{
    public function __construct(
        public EventId $eventId,
        public ActivityId $activityId,
        public VariantInput $input,
    ) {}
}
