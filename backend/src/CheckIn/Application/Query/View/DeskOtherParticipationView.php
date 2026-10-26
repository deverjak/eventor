<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query\View;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Another participation of the same person: the parent whose children race on another day.
 */
final readonly class DeskOtherParticipationView
{
    public function __construct(
        public ParticipationId $participationId,
        public ActivityId $activityId,
        public string $activityName,
        public ?\DateTimeImmutable $startsAt,
        public string $state,
    ) {}
}
