<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;

/**
 * Domain event: a queue item closed because Pairrot allocated or ignored the transaction. Translated into
 * `payments.transaction_allocated` when it was allocated to a known request.
 */
final readonly class UnmatchedTransactionClosed
{
    public function __construct(
        public OrganizerId $organizerId,
        public BankTransactionId $transactionId,
        public string $resolution,
        public ?PaymentRequestId $paymentRequestId,
        public ?OrderId $orderId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
