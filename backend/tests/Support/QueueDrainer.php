<?php

declare(strict_types=1);

namespace Eventor\Tests\Support;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Test helper: runs a real Messenger Worker in the test process until every consumable transport is empty.
 * It uses the kernel's event dispatcher, so retry, failure-transport and DeadLetterListener behave exactly as in
 * the `worker` container; in the test environment the `<module>_events` transports are `in-memory://`
 * (`probe_*` transports used by the outbox proof are Doctrine transports). Registered as a public service
 * in config/services_test.php.
 */
final class QueueDrainer
{
    /**
     * @param ServiceLocator<object> $receivers
     */
    public function __construct(
        #[AutowireLocator('messenger.receiver', indexAttribute: 'alias')]
        private readonly ServiceLocator $receivers,
        #[Autowire(service: 'messenger.routable_message_bus')]
        private readonly MessageBusInterface $bus,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    /**
     * @param list<string>|null $transports names to consume; default: every transport except `failed` and the scheduler
     * @param int               $limit      safety net against message loops
     */
    public function drain(?array $transports = null, int $limit = 500): DrainResult
    {
        $names = $transports ?? array_values(array_filter(
            array_keys($this->receivers->getProvidedServices()),
            static fn(string $name): bool => 'failed' !== $name && !str_starts_with($name, 'scheduler_'),
        ));

        /** @var array<string, ReceiverInterface> $receivers */
        $receivers = [];
        foreach ($names as $name) {
            $receiver = $this->receivers->get($name);
            \assert($receiver instanceof ReceiverInterface);
            $receivers[$name] = $receiver;
        }
        if ([] === $receivers) {
            return new DrainResult(0, 0);
        }

        $handled = 0;
        $failed = 0;
        $stopWhenIdle = static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        };
        $countHandled = static function (WorkerMessageHandledEvent $event) use (&$handled, $limit, &$failed): void {
            if (++$handled + $failed > $limit) {
                throw new \LogicException(\sprintf('QueueDrainer processed more than %d messages; is there a message loop?', $limit));
            }
        };
        $countFailed = static function (WorkerMessageFailedEvent $event) use (&$failed, &$handled, $limit): void {
            if (++$failed + $handled > $limit) {
                throw new \LogicException(\sprintf('QueueDrainer processed more than %d messages; is there a message loop?', $limit));
            }
        };

        $this->dispatcher->addListener(WorkerRunningEvent::class, $stopWhenIdle, 1_000);
        $this->dispatcher->addListener(WorkerMessageHandledEvent::class, $countHandled, 1_000);
        $this->dispatcher->addListener(WorkerMessageFailedEvent::class, $countFailed, 1_000);

        try {
            (new Worker($receivers, $this->bus, $this->dispatcher))->run(['sleep' => 0]);
        } finally {
            $this->dispatcher->removeListener(WorkerRunningEvent::class, $stopWhenIdle);
            $this->dispatcher->removeListener(WorkerMessageHandledEvent::class, $countHandled);
            $this->dispatcher->removeListener(WorkerMessageFailedEvent::class, $countFailed);
        }

        return new DrainResult($handled, $failed);
    }

    /**
     * Messages that exhausted their retries (in-memory `failed` transport of the test environment).
     *
     * @return list<object>
     */
    public function deadLetters(): array
    {
        $failed = $this->receivers->get('failed');
        \assert($failed instanceof InMemoryTransport);

        return array_values(array_map(static fn(Envelope $envelope): object => $envelope->getMessage(), $failed->getSent()));
    }
}
