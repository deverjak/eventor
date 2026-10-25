<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

/**
 * Applies a `payment_request.updated` (or reconcile snapshot) from the inbox: the only way Pairrot money reaches the
 * ledger (chapter 07). One transaction, the payment request row locked.
 */
final readonly class ApplyPairrotPaymentUpdate
{
    public function __construct(public string $inboxMessageId) {}
}
