<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Payments\Contract\Event\TransactionAllocated;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The transaction was allocated: the reconciliation item closes.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnTransactionAllocated
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(TransactionAllocated $event): void
    {
        $this->attention->close(AttentionType::UnmatchedPayment, $event->organizerId, 'bank_transaction', $event->transactionId->toString());
    }
}
