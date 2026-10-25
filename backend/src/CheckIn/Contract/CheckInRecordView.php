<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract;

use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * A valid check-in. `checkedInBy` is the account id of the operator (never an e-mail), null for a device token.
 */
final readonly class CheckInRecordView
{
    public function __construct(
        public CheckInId $id,
        public ParticipationId $participationId,
        public CheckInListId $listId,
        public ?CheckInStationId $stationId,
        public \DateTimeImmutable $checkedInAt,
        public ?string $checkedInBy,
    ) {}
}
