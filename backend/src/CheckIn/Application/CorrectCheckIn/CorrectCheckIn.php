<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\CorrectCheckIn;

/**
 * A manager corrects a check-in (CHK-07): the old row is voided and a new valid one takes over with the corrected
 * station and/or time (null = keep). The reason is mandatory and audited.
 */
final readonly class CorrectCheckIn
{
    public function __construct(
        public string $eventId,
        public string $checkInId,
        public string $reason,
        public ?string $stationId,
        public ?\DateTimeImmutable $checkedInAt,
        public string $actorAccountId,
    ) {}
}
