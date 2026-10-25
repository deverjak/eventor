<?php

declare(strict_types=1);

namespace Eventor\Tests\Support;

use Doctrine\DBAL\Connection;
use Eventor\Shared\Application\Messaging\CommandBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Base of integration tests: a booted test kernel on the per-agent database (`TEST_TOKEN`), every test inside a
 * rolled-back transaction (dama/doctrine-test-bundle), `MockClock` available through ClockSensitiveTrait.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesOwners;
    use FetchesServices;
    use ReadsDatabase;

    public const string PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
    }

    protected function commandBus(): CommandBus
    {
        $bus = static::getContainer()->get('test.command_bus');
        \assert($bus instanceof CommandBus);

        return $bus;
    }

    protected function connection(): Connection
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }

    protected function queues(): QueueDrainer
    {
        $drainer = static::getContainer()->get(QueueDrainer::class);
        \assert($drainer instanceof QueueDrainer);

        return $drainer;
    }

    /** Runs a worker in-process until all consumable transports are empty (retries and dead letters included). */
    protected function drainQueues(): DrainResult
    {
        return $this->queues()->drain();
    }
}
