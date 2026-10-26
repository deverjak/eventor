<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\RecordCheckIn;

/**
 * The operator confirms an arrival (CHK-05). `$actorAccountId` is the signed-in team member, `$deviceLabel` the device
 * token (exactly one of them is set). `$acknowledgeWarning` lets a participant with a warning (unpaid) in.
 */
final readonly class RecordCheckIn
{
    public function __construct(
        public string $eventId,
        public string $listId,
        public string $participationId,
        public ?string $stationId,
        public bool $acknowledgeWarning,
        public ?string $actorAccountId,
        public ?string $deviceLabel,
    ) {}
}
