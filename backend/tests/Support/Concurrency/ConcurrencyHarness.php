<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Concurrency;

use Doctrine\DBAL\Connection;
use Symfony\Component\Process\Process;

/**
 * Proves behaviour under real concurrency (S01, S10): N operating-system processes, each a separate PHP with its own
 * database connection, run the same console command at the same moment. A transaction inside one PHP process proves
 * nothing about races, so the tests built on this class run WITHOUT the dama rollback wrapper (the processes must see
 * committed rows) and clean their tables afterwards.
 *
 * How the start is synchronised: the test closes an advisory-lock barrier, starts the processes, waits until all of
 * them are blocked on it (pg_locks), then opens it. The command must call `AdvisoryBarrier::wait()` for the `--barrier`
 * option that this class appends, and print one JSON line as its last output line: `{"ok":true}` or
 * `{"ok":false,"code":"capacity_exhausted"}`.
 *
 *     $results = ConcurrencyHarness::run($connection, 8, 'eventor:test:capacity-claim', ['--bucket=…', '--seats=1']);
 *     self::assertCount(1, $results->succeeded());
 */
final class ConcurrencyHarness
{
    /**
     * @param list<string>|\Closure(int): list<string> $arguments the same for every process, or computed from the process index
     */
    public static function run(Connection $control, int $processes, string $command, array|\Closure $arguments = [], float $timeoutSeconds = 90.0): ConcurrencyResults
    {
        if ($processes < 2) {
            throw new \InvalidArgumentException('A race needs at least two processes.');
        }

        // Boot the kernel once, serially, so that a stale compiled container is rebuilt by one process and not by N at
        // once (they would race on the cache directory and also make the barrier wait for the slowest rebuild).
        $warmUp = new Process([\PHP_BINARY, 'bin/console', 'list', '--env=test', '--short'], \dirname(__DIR__, 3), ['APP_ENV' => 'test', 'APP_DEBUG' => '0', 'TEST_TOKEN' => self::token()], null, $timeoutSeconds);
        $warmUp->mustRun();

        $key = AdvisoryBarrier::newKey();
        AdvisoryBarrier::close($control, $key);

        /** @var list<Process> $running */
        $running = [];
        try {
            foreach (range(0, $processes - 1) as $index) {
                $args = $arguments instanceof \Closure ? $arguments($index) : $arguments;
                $process = new Process(
                    [\PHP_BINARY, 'bin/console', $command, '--env=test', '--no-interaction', ...$args, '--barrier='.$key],
                    \dirname(__DIR__, 3),
                    ['APP_ENV' => 'test', 'APP_DEBUG' => '0', 'TEST_TOKEN' => self::token()],
                    null,
                    $timeoutSeconds,
                );
                $process->start();
                $running[] = $process;
            }

            self::awaitBarrier($control, $key, $processes, $running, $timeoutSeconds);
        } catch (\Throwable $e) {
            foreach ($running as $process) {
                $process->stop(0);
            }
            throw $e;
        } finally {
            AdvisoryBarrier::open($control, $key);
        }

        $outcomes = [];
        foreach ($running as $index => $process) {
            $process->wait();
            $outcomes[] = ProcessOutcome::fromOutput($index, $process->getExitCode() ?? -1, $process->getOutput(), $process->getErrorOutput());
        }

        return new ConcurrencyResults($outcomes);
    }

    /**
     * @param list<Process> $running
     */
    private static function awaitBarrier(Connection $control, int $key, int $expected, array $running, float $timeoutSeconds): void
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (AdvisoryBarrier::waiting($control, $key) < $expected) {
            foreach ($running as $index => $process) {
                if (!$process->isRunning()) {
                    throw new \RuntimeException(\sprintf("Process #%d ended before the barrier opened:\n%s%s", $index, $process->getOutput(), $process->getErrorOutput()));
                }
            }
            if (microtime(true) > $deadline) {
                throw new \RuntimeException(\sprintf('Only %d of %d processes reached the barrier in time.', AdvisoryBarrier::waiting($control, $key), $expected));
            }
            usleep(20_000);
        }
    }

    private static function token(): string
    {
        $token = $_SERVER['TEST_TOKEN'] ?? 'default';

        return \is_string($token) && '' !== $token ? $token : 'default';
    }
}
