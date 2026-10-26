<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Registration;

use Eventor\Payments\Application\Webhook\ReceivePairrotWebhook;
use Eventor\Payments\Infrastructure\Pairrot\InMemoryPaymentGateway;
use Eventor\Registration\Application\Expire\ExpireReservations;
use Eventor\Tests\Support\DrainResult;
use Eventor\Tests\Support\QueueDrainer;

/**
 * Base of the Registration scenarios (S01, S02, S05, S06, S13, reservation expiry): the HTTP stack of
 * RegistrationHttpTestCase with a frozen MockClock at 2026-11-01 12:00 (Prague), the in-memory `<module>_events`
 * transports drained in-process by a real Messenger worker, and the in-memory Pairrot, so a payment is a signed webhook
 * delivered to the inbox exactly as in production.
 */
abstract class RegistrationScenarioCase extends RegistrationHttpTestCase
{
    protected const string START = '2026-11-01 12:00:00 Europe/Prague';
    protected const string ACCOUNT = '19-2000145399/0800';

    protected function setUp(): void
    {
        self::mockTime(static::START);
        parent::setUp();
        // One kernel for the whole scenario: the in-memory Pairrot and the queues keep their state between requests.
        $this->client->disableReboot();
    }

    /** Delivers every queued message and fails the test when a subscriber threw. */
    protected function drain(): DrainResult
    {
        $drainer = static::getContainer()->get(QueueDrainer::class);
        \assert($drainer instanceof QueueDrainer);
        $failures = [];
        $dispatcher = static::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof \Symfony\Component\EventDispatcher\EventDispatcherInterface);
        $listener = static function (\Symfony\Component\Messenger\Event\WorkerMessageFailedEvent $event) use (&$failures): void {
            $throwable = $event->getThrowable();
            $root = $throwable instanceof \Symfony\Component\Messenger\Exception\HandlerFailedException && [] !== $throwable->getWrappedExceptions()
                ? array_values($throwable->getWrappedExceptions())[0]
                : $throwable;
            // The failing probes of the outbox proof (tests/Fixtures) are not ours, nor is Communication, whose test
            // double of the Registration facades knows no orders of ours (it is judged by its own tests).
            if (str_starts_with($event->getReceiverName(), 'probe_') || 'communication_events' === $event->getReceiverName()) {
                return;
            }
            $failures[] = \sprintf('%s on %s: %s (%s:%d)', $event->getEnvelope()->getMessage()::class, $event->getReceiverName(), $root->getMessage(), basename($root->getFile()), $root->getLine());
        };
        $dispatcher->addListener(\Symfony\Component\Messenger\Event\WorkerMessageFailedEvent::class, $listener, 2000);
        try {
            $result = $drainer->drain();
        } finally {
            $dispatcher->removeListener(\Symfony\Component\Messenger\Event\WorkerMessageFailedEvent::class, $listener);
        }
        self::assertSame([], array_values(array_unique($failures)), 'A subscriber failed while draining the queues.');

        return $result;
    }

    /** The organizer "klub" connects its bank account (the onboarding wizard against the in-memory Pairrot). */
    protected function connectPairrot(): void
    {

        // Payments connects the account against the in-memory Pairrot; Identity (whose wizard runs against a test double) gets the same ids.
        $organizer = \Eventor\Shared\Domain\Id\OrganizerId::fromString($this->owner['organizerId']);
        $onboarding = $this->service(\Eventor\Payments\Contract\PairrotOnboarding::class);
        $subtenant = $onboarding->activateSubtenant($organizer, 'Klub Plzeň', 'klub@example.test');
        $connection = $onboarding->connectBankAccount($organizer, $subtenant, self::ACCOUNT, 'Klub');
        $this->connection()->executeStatement('UPDATE identity.organizer SET pairrot_subtenant_id = ?, pairrot_bank_connection_id = ?, masked_iban = ?, transfer_enabled = TRUE WHERE id = ?', [$subtenant, $connection->bankConnectionId, $connection->maskedIban, $organizer->toString()]);
    }

    protected function pairrot(): InMemoryPaymentGateway
    {
        $gateway = static::getContainer()->get(InMemoryPaymentGateway::class);
        \assert($gateway instanceof InMemoryPaymentGateway);

        return $gateway;
    }

    /** Pairrot reports a payment of `$minor` for the payment request of the order. */
    protected function payByTransfer(string $orderId, int $minor): void
    {
        $pairrotId = $this->dbString('SELECT pairrot_id FROM payments.payment_request WHERE order_id = ? AND attempt = 1', [$orderId]);
        $event = $this->pairrot()->receivePayment($pairrotId, $minor);
        $this->commandBus()->dispatch(new ReceivePairrotWebhook(json_encode($event, \JSON_THROW_ON_ERROR)));
    }

    /** The scheduler's tick, run directly. */
    protected function sweepReservations(): void
    {
        $this->commandBus()->dispatch(new ExpireReservations());
    }

    protected function participationState(string $participationId): string
    {
        return $this->dbString('SELECT state FROM registration.participation WHERE id = ?', [$participationId]);
    }

    protected function bucketUse(string $variantId): int
    {
        return $this->dbInt("SELECT held + confirmed FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$variantId]);
    }

    protected function heldSeats(string $variantId): int
    {
        return $this->dbInt("SELECT held FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$variantId]);
    }
}
