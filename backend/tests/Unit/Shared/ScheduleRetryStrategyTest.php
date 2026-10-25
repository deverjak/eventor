<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Shared\Infrastructure\Messenger\ScheduleRetryStrategy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class ScheduleRetryStrategyTest extends TestCase
{
    public function test_the_waiting_times_follow_the_schedule(): void
    {
        $strategy = new ScheduleRetryStrategy(jitter: 0.0);

        $waits = array_map(fn(int $retries): int => $strategy->getWaitingTime($this->after($retries)), [0, 1, 2, 3, 4]);

        // 1 min, 5 min, 30 min, 2 h, 12 h
        self::assertSame([60_000, 300_000, 1_800_000, 7_200_000, 43_200_000], $waits);
    }

    public function test_there_are_six_attempts_in_total(): void
    {
        $strategy = new ScheduleRetryStrategy();

        foreach ([0, 1, 2, 3, 4] as $retries) {
            self::assertTrue($strategy->isRetryable($this->after($retries)), "failure after $retries retries must be retried");
        }
        self::assertFalse($strategy->isRetryable($this->after(5)), 'the sixth failure is final');
    }

    public function test_jitter_stays_within_ten_percent(): void
    {
        $strategy = new ScheduleRetryStrategy();

        for ($i = 0; $i < 200; ++$i) {
            $wait = $strategy->getWaitingTime($this->after(1));
            self::assertGreaterThanOrEqual(270_000, $wait);
            self::assertLessThanOrEqual(330_000, $wait);
        }
    }

    public function test_an_unrecoverable_failure_is_not_retried(): void
    {
        $envelope = $this->after(0);
        $failure = new HandlerFailedException($envelope, [new UnrecoverableMessageHandlingException('invalid data')]);

        self::assertFalse((new ScheduleRetryStrategy())->isRetryable($envelope, $failure));
    }

    public function test_a_recoverable_failure_with_a_delay_overrides_the_schedule(): void
    {
        $envelope = $this->after(3);
        $failure = new HandlerFailedException($envelope, [new RecoverableMessageHandlingException('429 Retry-After', retryDelay: 7_000)]);

        self::assertSame(7_000, (new ScheduleRetryStrategy())->getWaitingTime($envelope, $failure));
    }

    private function after(int $retries): Envelope
    {
        $envelope = new Envelope(new \stdClass());
        for ($i = 1; $i <= $retries; ++$i) {
            $envelope = $envelope->with(new RedeliveryStamp($i));
        }

        return $envelope;
    }
}
