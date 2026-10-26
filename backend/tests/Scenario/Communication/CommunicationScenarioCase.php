<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\Communication;

use Eventor\Communication\Application\Scheduling\SweepCommunication;
use Eventor\Communication\Infrastructure\Mail\RecordingMailer;
use Eventor\Communication\UserInterface\EventHandler\SweepCommunicationSubscriber;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Support\DrainResult;
use Eventor\Tests\Support\QueueDrainer;
use Eventor\Tests\Support\Registration\RegistrationScenarioCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Base of the Communication scenarios: the real Registration (orders through the public endpoint), Catalog, Legal,
 * Payments (in-memory Pairrot) and Attention, with the recording mailer. Nothing of Communication's neighbours is
 * replaced by a double.
 */
abstract class CommunicationScenarioCase extends RegistrationScenarioCase
{
    protected function mailer(): RecordingMailer
    {
        $mailer = static::getContainer()->get(RecordingMailer::class);
        \assert($mailer instanceof RecordingMailer);

        return $mailer;
    }

    protected function organizer(): OrganizerId
    {
        return OrganizerId::fromString($this->owner['organizerId']);
    }

    /** Delivers every queued message and fails the test when any subscriber of any module threw. */
    /** @param list<string>|null $transports */
    protected function drainStrict(?array $transports = null): DrainResult
    {
        $handled = 0;
        $failed = 0;
        for ($round = 0; $round < 8; ++$round) {
            $result = $this->drainChecked(false, $transports);
            $handled += $result->handled;
            $failed += $result->failed;
            if (0 === $result->handled + $result->failed) {
                return new DrainResult($handled, $failed);
            }
        }

        throw new \LogicException('The strict queue drain did not become idle after eight rounds.');
    }

    /**
     * Drains like a worker that keeps running: a failing mail attempt (`async_mail`) is expected and retried after its
     * backoff (the clock jumps past the longest one, as the retry delays are honoured against the mock clock), anything
     * else failing fails the test.
     */
    protected function drainWithMailRetries(int $maxRounds = 8): DrainResult
    {
        $handled = 0;
        $failed = 0;
        for ($round = 0; $round < $maxRounds; ++$round) {
            $result = $this->drainChecked(true);
            $handled += $result->handled;
            $failed += $result->failed;
            if (0 === $result->handled + $result->failed) {
                break;
            }
            Clock::get()->sleep(3 * 3600);
        }

        return new DrainResult($handled, $failed);
    }

    /** The scheduler tick (deliveries that fell due, campaigns whose time has come), run directly. */
    protected function sweep(): void
    {
        $sweeper = static::getContainer()->get('test.'.SweepCommunicationSubscriber::class);
        \assert($sweeper instanceof SweepCommunicationSubscriber);
        $sweeper(new SweepCommunication());
    }

    /** Puts an integration event on the bus the way its publisher does. */
    protected function publishEvent(object $event): void
    {
        $bus = static::getContainer()->get('test.communication_event_bus');
        \assert($bus instanceof MessageBusInterface);
        $bus->dispatch($event, [TenantStamp::of($this->organizer())]);
    }

    /**
     * Places a one-person cash order for the trail through the public endpoint.
     *
     * @param array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string} $event
     *
     * @return array{orderId: string, participationId: string, placed: array<array-key, mixed>}
     */
    protected function orderFor(array $event, string $email, string $method = 'cash', string $first = 'Anna'): array
    {
        $placed = $this->placeOrder(self::orderBody($event, $first, $email, overrides: ['paymentMethod' => $method]), slug: $event['slug']);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        \assert(\is_array($placed) && \is_string($placed['orderId']));

        return ['orderId' => $placed['orderId'], 'participationId' => $this->dbString('SELECT id FROM registration.participation WHERE order_id = ?', [$placed['orderId']]), 'placed' => $placed];
    }

    /** @param list<string>|null $transports */
    private function drainChecked(bool $tolerateMail, ?array $transports = null): DrainResult
    {
        $drainer = static::getContainer()->get(QueueDrainer::class);
        \assert($drainer instanceof QueueDrainer);
        $dispatcher = static::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);

        $failures = [];
        $listener = static function (WorkerMessageFailedEvent $event) use (&$failures, $tolerateMail): void {
            $name = $event->getReceiverName();
            if (str_starts_with($name, 'probe_') || ($tolerateMail && 'async_mail' === $name)) {
                return;
            }
            $throwable = $event->getThrowable();
            $root = $throwable instanceof \Symfony\Component\Messenger\Exception\HandlerFailedException && [] !== $throwable->getWrappedExceptions()
                ? array_values($throwable->getWrappedExceptions())[0]
                : $throwable;
            $failures[] = \sprintf('%s on %s: %s (%s:%d)', $event->getEnvelope()->getMessage()::class, $name, $root->getMessage(), basename($root->getFile()), $root->getLine());
        };
        $dispatcher->addListener(WorkerMessageFailedEvent::class, $listener, 2000);
        try {
            $result = $drainer->drain($transports);
        } finally {
            $dispatcher->removeListener(WorkerMessageFailedEvent::class, $listener);
        }
        self::assertSame([], array_values(array_unique($failures)), 'A subscriber failed while draining the queues.');

        return $result;
    }
}
