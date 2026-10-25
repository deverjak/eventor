<?php

declare(strict_types=1);

namespace Eventor\Reporting\Contract;

/**
 * Participations of an event by lifecycle state.
 */
final readonly class ParticipationCounts
{
    public function __construct(
        public int $pending,
        public int $confirmed,
        public int $expired,
        public int $requiresAttention,
        public int $cancelled,
        public int $transferred,
    ) {}
}
