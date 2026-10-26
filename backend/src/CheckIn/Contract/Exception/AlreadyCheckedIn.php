<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\CheckInStationId;

/**
 * The participation already has a valid check-in (S10, CHK-06): the desk shows when, by whom and where. Only a manager can correct it (CHK-07).
 */
#[AsProblem(code: 'already_checked_in', status: 409)]
final class AlreadyCheckedIn extends DomainException
{
    public function __construct(
        public readonly \DateTimeImmutable $checkedInAt,
        public readonly ?string $checkedInBy,
        public readonly ?CheckInStationId $station,
        public readonly ?string $deviceLabel = null,
    ) {
        parent::__construct('The participant is already checked in.');
    }

    /**
     * @return array<string, scalar|null>
     */
    public function context(): array
    {
        return [
            'checkedInAt' => $this->checkedInAt->format(\DATE_ATOM),
            'checkedInBy' => $this->checkedInBy,
            'stationId' => $this->station?->toString(),
            'deviceLabel' => $this->deviceLabel,
        ];
    }
}
