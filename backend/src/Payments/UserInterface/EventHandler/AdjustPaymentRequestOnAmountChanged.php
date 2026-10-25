<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\EventHandler;

use Eventor\Payments\Application\PaymentRequest\PaymentRequestAdjuster;
use Eventor\Registration\Contract\Event\OrderAmountChanged;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `registration.order_amount_changed` -> `payments.recompute_financial_state` + `payments.adjust_payment_request`
 * (S18, REG-14, event cancellation): the ledger is folded against the new amount, then the requests in Pairrot are
 * cancelled and reissued, or a supplementary request is issued, according to what was paid.
 */
#[AsMessageHandler(fromTransport: 'payments_events')]
final readonly class AdjustPaymentRequestOnAmountChanged
{
    public function __construct(private PaymentRequestAdjuster $adjuster) {}

    public function __invoke(OrderAmountChanged $event): void
    {
        $this->adjuster->adjust($event->organizerId, $event->orderId, $event->newAmountDue, $event->reason->value);
    }
}
