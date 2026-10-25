<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Messenger;

use Eventor\Payments\Application\PaymentRequest\FailPendingPaymentRequests;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayException;
use Eventor\Registration\Contract\Event\OrderAmountChanged;
use Eventor\Registration\Contract\Event\OrderPlaced;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * S08: after the last retry of `registration.order_placed` / `order_amount_changed` (the outbox gave up, the message
 * goes to `failed`), the payment requests of that order that are still PENDING become FAILED and
 * `payments.payment_request_failed` is published. Attention then offers "try again". The order itself is untouched.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
#[WithMonologChannel('pairrot')]
final readonly class FailPaymentRequestsWhenRetriesAreExhausted
{
    public function __construct(
        private CommandBus $commands,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry() || 'payments_events' !== $event->getReceiverName()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof OrderPlaced && !$message instanceof OrderAmountChanged) {
            return;
        }

        try {
            $this->commands->dispatch(
                new FailPendingPaymentRequests($message->orderId->toString(), $this->errorCode($event->getThrowable())),
                TenantStamp::of($message->organizerId),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Could not mark the payment requests of an order as failed.', ['exception' => $e]);
        }
    }

    private function errorCode(\Throwable $throwable): string
    {
        $root = $throwable instanceof HandlerFailedException && [] !== $throwable->getWrappedExceptions()
            ? array_values($throwable->getWrappedExceptions())[0]
            : $throwable;

        return $root instanceof PaymentGatewayException ? $root->errorCode() : $root::class;
    }
}
