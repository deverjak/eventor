<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query\View;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * A search result of the desk (CHK-02). The name is personal data, shown to the operator only.
 */
final readonly class DeskHitView
{
    public function __construct(
        public ParticipationId $participationId,
        public string $displayName,
        public string $registrationCode,
        public ActivityId $activityId,
        public string $state,
        public ?\DateTimeImmutable $checkedInAt,
    ) {}
}
