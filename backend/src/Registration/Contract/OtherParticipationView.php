<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Another participation of the same person (parent with children on different days, chapter 04 path 5).
 */
final readonly class OtherParticipationView
{
    public function __construct(
        public ParticipationId $participationId,
        public ActivityId $activityId,
        public string $activityName,
        public ?\DateTimeImmutable $startsAt,
        public ParticipationState $state,
    ) {}
}
