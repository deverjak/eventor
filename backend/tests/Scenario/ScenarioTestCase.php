<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario;

use Eventor\Tests\Support\IntegrationTestCase;

/**
 * Base of the acceptance scenarios (S01-S18 and phase proofs): the whole behaviour across modules, with the
 * in-memory `<module>_events` transports, a frozen `MockClock` and queues drained in-process by a real Messenger
 * worker (`drainQueues()`), so subscribers run exactly as in production. Each scenario is one test class named
 * after the scenario (`S05_LatePaymentCapacityFullTest`).
 *
 *     public function test_something(): void
 *     {
 *         $this->commandBus()->dispatch(...);
 *         self::mockTime('+2 hours');     // expire reservations, send reminders, ... without sleep()
 *         $this->drainQueues();           // deliver everything that was published
 *     }
 */
abstract class ScenarioTestCase extends IntegrationTestCase
{
    /** Scenarios start at a fixed, timezone-explicit instant so expiry and reminder logic is deterministic. */
    protected const string START = '2026-11-01 12:00:00 Europe/Prague';

    protected function setUp(): void
    {
        self::mockTime(static::START);
        parent::setUp();
    }
}
