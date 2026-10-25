<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Messenger;

use Eventor\Shared\Application\Attention\AttentionRaiser;
use Eventor\Shared\Application\Attention\AttentionRequest;
use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Application\Messaging\Stamp\CorrelationStamp;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Uid\Uuid;

/**
 * Every message that exhausts its retries (and is moved to the `failed` transport by Symfony) becomes an
 * AttentionItem `outbox_dead_letter` (ADR 003), so nothing dies silently in a log.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
#[WithMonologChannel('outbox')]
final readonly class DeadLetterListener
{
    public function __construct(
        private AttentionRaiser $attention,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        // Failures while retrying from the failure transport itself are not new dead letters.
        if ($event->willRetry() || 'failed' === $event->getReceiverName()) {
            return;
        }

        $envelope = $event->getEnvelope();
        $messageClass = $envelope->getMessage()::class;
        $tenant = $envelope->last(TenantStamp::class);
        $rawId = $envelope->last(TransportMessageIdStamp::class)?->getId();
        $messageId = \is_scalar($rawId) ? (string) $rawId : null;

        try {
            $this->attention->raise(new AttentionRequest(
                AttentionType::OutboxDeadLetter,
                \sprintf('dead_letter:%s:%s:%s', $event->getReceiverName(), $messageClass, $messageId ?? Uuid::v7()->toRfc4122()),
                $tenant?->organizer(),
                'message',
                $messageId,
                [
                    'message' => $messageClass,
                    'transport' => $event->getReceiverName(),
                    'error' => $this->rootCause($event->getThrowable())::class,
                    'correlationId' => $envelope->last(CorrelationStamp::class)?->correlationId,
                ],
            ));
        } catch (\Throwable $e) {
            // The worker must survive a failing attention queue; the message is in `failed` anyway.
            $this->logger->error('Could not open an attention item for a dead-lettered message.', [
                'message' => $messageClass,
                'exception' => $e,
            ]);
        }
    }

    private function rootCause(\Throwable $throwable): \Throwable
    {
        if ($throwable instanceof HandlerFailedException && [] !== $throwable->getWrappedExceptions()) {
            return array_values($throwable->getWrappedExceptions())[0];
        }

        return $throwable;
    }
}
