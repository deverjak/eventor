<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Rule;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\CheckInFacts;
use Eventor\CheckIn\Domain\CheckInRule;
use Eventor\CheckIn\Domain\CheckInVerdict;
use Eventor\CheckIn\Domain\ParticipationStanding;

/**
 * Cancelled, expired or transferred participations do not check in (CHK-03); the QR code of such a participation is dead anyway.
 */
final readonly class CancelledOrExpiredRule implements CheckInRule
{
    public function judge(CheckInFacts $facts): ?CheckInVerdict
    {
        return ParticipationStanding::Inactive === $facts->standing ? CheckInVerdict::block(CheckInSituation::CancelledOrExpired) : null;
    }
}
