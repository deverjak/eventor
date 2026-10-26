<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Rule;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\CheckInFacts;
use Eventor\CheckIn\Domain\CheckInRule;
use Eventor\CheckIn\Domain\CheckInVerdict;
use Eventor\CheckIn\Domain\ParticipationStanding;

/**
 * The organizer has an open problem with this participation (for example a late payment): the operator cannot settle it at the desk.
 */
final readonly class RequiresAttentionRule implements CheckInRule
{
    public function judge(CheckInFacts $facts): ?CheckInVerdict
    {
        return ParticipationStanding::RequiresAttention === $facts->standing ? CheckInVerdict::escalate(CheckInSituation::RequiresAttention) : null;
    }
}
