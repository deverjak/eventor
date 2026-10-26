<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain;

/**
 * Everything the rules need to judge one arrival, already reduced to plain facts (built by `CheckInAssessor`).
 */
final readonly class CheckInFacts
{
    public function __construct(
        public bool $alreadyCheckedIn,
        public bool $sameActivity,
        public ParticipationStanding $standing,
        public bool $answersComplete,
        public bool $confirmationsComplete,
        public bool $paid,
    ) {}

    /**
     * A participation that is fine in every respect (tests build their variants from it).
     */
    public static function clean(): self
    {
        return new self(false, true, ParticipationStanding::Confirmed, true, true, true);
    }
}
