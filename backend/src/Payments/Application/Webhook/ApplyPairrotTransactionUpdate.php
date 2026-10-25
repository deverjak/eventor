<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

/**
 * Applies `transaction.recorded` / `transaction.updated` to the reconciliation queue (PAY-06). Never touches the ledger.
 */
final readonly class ApplyPairrotTransactionUpdate
{
    public function __construct(public string $inboxMessageId) {}
}
