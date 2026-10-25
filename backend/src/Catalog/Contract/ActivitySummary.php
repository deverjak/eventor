<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\ActivityId;

/**
 * Display data of one activity.
 */
final readonly class ActivitySummary
{
    public function __construct(
        public ActivityId $activityId,
        public string $name,
        public ?\DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public RegistrationState $registrationState,
    ) {}
}
