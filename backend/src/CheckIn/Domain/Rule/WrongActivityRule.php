<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Rule;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\CheckInFacts;
use Eventor\CheckIn\Domain\CheckInRule;
use Eventor\CheckIn\Domain\CheckInVerdict;

/**
 * The participation belongs to another activity (another day): the parents of the children racing tomorrow are not admitted to todays list. The desk shows where the participant belongs.
 */
final readonly class WrongActivityRule implements CheckInRule
{
    public function judge(CheckInFacts $facts): ?CheckInVerdict
    {
        return !$facts->sameActivity ? CheckInVerdict::block(CheckInSituation::WrongActivity) : null;
    }
}
