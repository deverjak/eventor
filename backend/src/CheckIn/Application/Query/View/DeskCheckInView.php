<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query\View;

use Eventor\Shared\Domain\Id\CheckInId;

/**
 * "Already checked in at HH:MM by X" (CHK-06): `checkedInBy` is the account id of the operator, `deviceLabel` the label
 * of the device token when a device scanned.
 */
final readonly class DeskCheckInView
{
    public function __construct(
        public CheckInId $checkInId,
        public \DateTimeImmutable $checkedInAt,
        public ?string $checkedInBy,
        public ?string $deviceLabel,
        public ?string $stationId,
        public ?int $cashMinor,
    ) {}
}
