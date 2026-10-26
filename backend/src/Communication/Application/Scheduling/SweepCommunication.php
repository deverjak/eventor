<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Scheduling;

/**
 * The scheduled tick (every minute, `CommunicationSchedule`): queue due deliveries, start due campaigns. A sweeper
 * walks the current state each time, so a missed or doubled tick does no harm.
 */
final readonly class SweepCommunication {}
