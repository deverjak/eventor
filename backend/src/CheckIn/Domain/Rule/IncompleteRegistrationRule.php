<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Rule;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\CheckInFacts;
use Eventor\CheckIn\Domain\CheckInRule;
use Eventor\CheckIn\Domain\CheckInVerdict;

/**
 * The registration lacks answers to required form fields (CHK-09: incomplete registration).
 */
final readonly class IncompleteRegistrationRule implements CheckInRule
{
    public function judge(CheckInFacts $facts): ?CheckInVerdict
    {
        return !$facts->answersComplete ? CheckInVerdict::escalate(CheckInSituation::RequiresAttention) : null;
    }
}
