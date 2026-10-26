<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Rule;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\CheckInFacts;
use Eventor\CheckIn\Domain\CheckInRule;
use Eventor\CheckIn\Domain\CheckInVerdict;

/**
 * The participant has a valid check-in already (CHK-06): the desk shows when and by whom; only a manager can correct it.
 */
final readonly class AlreadyCheckedInRule implements CheckInRule
{
    public function judge(CheckInFacts $facts): ?CheckInVerdict
    {
        return $facts->alreadyCheckedIn ? CheckInVerdict::block(CheckInSituation::AlreadyCheckedIn) : null;
    }
}
