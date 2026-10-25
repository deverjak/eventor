<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Payments;

/**
 * Draining of the Payments queues in an IntegrationTestCase / ScenarioTestCase: a failing Payments subscriber fails
 * the test with its message, and the mock clock can jump past the outbox retry delays.
 */
trait DrainsPaymentsQueues
{
    /** Set to true by a test that provokes failures on purpose (Pairrot outage); otherwise any failing subscriber fails the test. */
    protected bool $tolerateWorkerFailures = false;

    /**
     * Drains the queues like the base class, but a subscriber that throws fails the test with its message, instead of
     * leaving a puzzling "no row" assertion further down.
     */
    protected function drainQueues(): \Eventor\Tests\Support\DrainResult
    {
        /** @var list<string> $failures */
        $failures = [];
        $dispatcher = static::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof \Symfony\Component\EventDispatcher\EventDispatcherInterface);
        $listener = static function (\Symfony\Component\Messenger\Event\WorkerMessageFailedEvent $event) use (&$failures): void {
            $throwable = $event->getThrowable();
            $root = $throwable instanceof \Symfony\Component\Messenger\Exception\HandlerFailedException && [] !== $throwable->getWrappedExceptions()
                ? array_values($throwable->getWrappedExceptions())[0]
                : $throwable;
            if (\in_array($event->getReceiverName(), ['payments_events', 'pairrot_inbox'], true)) {
                $failures[] = \sprintf('%s on %s: %s (%s:%d)', $event->getEnvelope()->getMessage()::class, $event->getReceiverName(), $root->getMessage(), basename($root->getFile()), $root->getLine());
            }
        };
        $dispatcher->addListener(\Symfony\Component\Messenger\Event\WorkerMessageFailedEvent::class, $listener, 2000);
        try {
            $result = $this->queues()->drain();
        } finally {
            $dispatcher->removeListener(\Symfony\Component\Messenger\Event\WorkerMessageFailedEvent::class, $listener);
        }
        if ([] !== $failures && !$this->tolerateWorkerFailures) {
            self::fail("A subscriber failed:\n".implode("\n", $failures));
        }

        return $result;
    }

    /**
     * Drains until nothing is left, jumping the mock clock past every retry delay of the outbox (1 m, 5 m, 30 m, 2 h,
     * 12 h): the in-memory transports honour `DelayStamp` against the clock. Needs a frozen `MockClock` (ScenarioTestCase).
     */
    protected function drainWithRetries(int $maxRounds = 8): \Eventor\Tests\Support\DrainResult
    {
        $handled = 0;
        $failed = 0;
        for ($round = 0; $round < $maxRounds; ++$round) {
            $result = $this->drainQueues();
            $handled += $result->handled;
            $failed += $result->failed;
            $before = $handled + $failed;
            // 12 h retry delay +10 % jitter = 13.2 h: jump 14 h so the last retry is always due (13 h made this test flaky).
            \Symfony\Component\Clock\Clock::get()->sleep(14 * 3600);
            $again = $this->drainQueues();
            $handled += $again->handled;
            $failed += $again->failed;
            if ($handled + $failed === $before) {
                break;
            }
        }

        return new \Eventor\Tests\Support\DrainResult($handled, $failed);
    }
}
