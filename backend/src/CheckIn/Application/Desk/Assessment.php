<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Desk;

use Eventor\CheckIn\Domain\CheckInRecord;
use Eventor\CheckIn\Domain\CheckInVerdict;
use Eventor\Registration\Contract\ParticipationCheckInView;

/**
 * What the desk knows about one participant on one list: the Registration projection, the valid check-in if any and
 * the verdict of the exception matrix.
 */
final readonly class Assessment
{
    public function __construct(
        public ParticipationCheckInView $participation,
        public CheckInVerdict $verdict,
        public ?CheckInRecord $existing,
    ) {}
}
