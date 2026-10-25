<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * Retry schedule of every `*_events` transport (chapter 06): 1 min, 5 min, 30 min, 2 h, 12 h, then the message goes
 * to the failure transport (six attempts in total). ±10 % jitter spreads retries after an outage. An unrecoverable
 * exception fails at once, a RecoverableMessageHandlingException with a delay (Pairrot 429 Retry-After) wins over
 * the schedule.
 */
final readonly class ScheduleRetryStrategy implements RetryStrategyInterface
{
    /** Milliseconds to wait before retry n (n = number of earlier retries). */
    public const array DELAYS_MS = [60_000, 300_000, 1_800_000, 7_200_000, 43_200_000];

    public function __construct(private float $jitter = 0.1) {}

    public function isRetryable(Envelope $message, ?\Throwable $throwable = null): bool
    {
        foreach ($this->causes($throwable) as $cause) {
            if ($cause instanceof UnrecoverableExceptionInterface) {
                return false;
            }
        }

        return RedeliveryStamp::getRetryCountFromEnvelope($message) < \count(self::DELAYS_MS);
    }

    public function getWaitingTime(Envelope $message, ?\Throwable $throwable = null): int
    {
        foreach ($this->causes($throwable) as $cause) {
            if ($cause instanceof RecoverableMessageHandlingException && null !== $cause->getRetryDelay()) {
                return $cause->getRetryDelay();
            }
        }

        $retries = RedeliveryStamp::getRetryCountFromEnvelope($message);
        $base = self::DELAYS_MS[min($retries, \count(self::DELAYS_MS) - 1)];
        $spread = (int) round($base * $this->jitter);

        return 0 === $spread ? $base : $base + random_int(-$spread, $spread);
    }

    /**
     * @return list<\Throwable>
     */
    private function causes(?\Throwable $throwable): array
    {
        if (null === $throwable) {
            return [];
        }

        return $throwable instanceof HandlerFailedException
            ? [$throwable, ...array_values($throwable->getWrappedExceptions())]
            : [$throwable];
    }
}
