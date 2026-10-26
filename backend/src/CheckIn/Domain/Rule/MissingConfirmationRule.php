<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Rule;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\CheckInFacts;
use Eventor\CheckIn\Domain\CheckInRule;
use Eventor\CheckIn\Domain\CheckInVerdict;

/**
 * A required confirmation (health declaration, consent) is missing; a manager resolves it (CHK-09).
 */
final readonly class MissingConfirmationRule implements CheckInRule
{
    public function judge(CheckInFacts $facts): ?CheckInVerdict
    {
        return !$facts->confirmationsComplete ? CheckInVerdict::escalate(CheckInSituation::MissingConfirmation) : null;
    }
}
