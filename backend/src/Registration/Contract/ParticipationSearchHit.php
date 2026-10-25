<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * A search result at the check-in desk. `displayName` is personal data for the operator only.
 */
final readonly class ParticipationSearchHit
{
    public function __construct(
        public ParticipationId $participationId,
        public string $displayName,
        public string $registrationCode,
        public ActivityId $activityId,
        public ParticipationState $state,
    ) {}
}
