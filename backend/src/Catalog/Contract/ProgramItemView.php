<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\ProgramItemId;

/**
 * One program item. `activityId` null = for every participant of the event.
 */
final readonly class ProgramItemView
{
    public function __construct(
        public ProgramItemId $id,
        public EventId $eventId,
        public ?ActivityId $activityId,
        public string $title,
        public ?string $description,
        public \DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public ?string $location,
        public ProgramVisibility $visibility,
    ) {}
}
