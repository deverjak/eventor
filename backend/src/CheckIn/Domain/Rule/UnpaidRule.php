<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Rule;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\CheckInFacts;
use Eventor\CheckIn\Domain\CheckInRule;
use Eventor\CheckIn\Domain\CheckInVerdict;

/**
 * Not paid in full: the operator warns the participant and may take cash on the spot (CHK-08) or let them in after acknowledging.
 */
final readonly class UnpaidRule implements CheckInRule
{
    public function judge(CheckInFacts $facts): ?CheckInVerdict
    {
        return !$facts->paid ? CheckInVerdict::warn(CheckInSituation::Unpaid) : null;
    }
}
