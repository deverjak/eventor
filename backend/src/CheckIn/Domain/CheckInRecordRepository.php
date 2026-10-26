<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain;

use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\ParticipationId;

interface CheckInRecordRepository
{
    public function add(CheckInRecord $record): void;

    /**
     * The row, locked `FOR UPDATE` (void and correction of the same row serialise).
     */
    public function lock(CheckInId $id): ?CheckInRecord;

    /**
     * The valid (not voided) check-in of a participation.
     */
    public function validFor(ParticipationId $participation): ?CheckInRecord;

    /**
     * Serialises the check-ins of one participation until the transaction ends. Called before `validFor()` and `add()`,
     * so the second of two racing operators waits, then sees the winner's row and answers "already checked in" (S10)
     * instead of colliding with the unique index and aborting its transaction.
     */
    public function lockParticipation(ParticipationId $participation): void;

    /**
     * Writes pending changes now. The void of a row must reach the database before the replacement is inserted,
     * because the unique index admits one valid row per participation at any moment.
     */
    public function flush(): void;
}
