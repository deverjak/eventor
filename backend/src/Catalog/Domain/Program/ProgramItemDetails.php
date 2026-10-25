<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Program;

use Eventor\Catalog\Contract\ProgramVisibility;
use Eventor\Catalog\Domain\Exception\InvalidCatalogData;
use Eventor\Shared\Domain\Id\ActivityId;

/**
 * What the organizer edits about a program item (PRG-01).
 */
final readonly class ProgramItemDetails
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $location,
        public ?string $responsible,
        public \DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public ?ActivityId $activityId,
        public ProgramVisibility $visibility,
    ) {
        if (null !== $endsAt && $endsAt < $startsAt) {
            throw InvalidCatalogData::because('A program item cannot end before it starts.');
        }
    }
}
