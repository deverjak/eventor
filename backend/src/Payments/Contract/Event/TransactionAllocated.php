<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A previously unmatched transaction was allocated to a payment request; the reconciliation item closes.
 *
 * P0. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.transaction_allocated', version: 1)]
#[PendingSubscribers('attention')]
final readonly class TransactionAllocated
{
    public function __construct(
        public OrganizerId $organizerId,
        public BankTransactionId $transactionId,
        public PaymentRequestId $paymentRequestId,
        public OrderId $orderId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
