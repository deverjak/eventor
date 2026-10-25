<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Payments\Contract\Event\UnmatchedTransactionReceived;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A bank transaction Pairrot could not allocate (PAY-06) enters the queue.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnUnmatchedTransactionReceived
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(UnmatchedTransactionReceived $event): void
    {
        $this->attention->open(AttentionType::UnmatchedPayment, $event->organizerId, 'bank_transaction', $event->transactionId->toString(), [
            'amountMinor' => $event->amount->minor,
            'currency' => $event->amount->currency->value,
            'variableSymbol' => $event->variableSymbol,
            'matchingResult' => $event->matchingResult->value,
        ]);
    }
}
