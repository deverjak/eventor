<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Refund;

/**
 * System command of the `payments.overpayment_detected` subscriber: opens (or adjusts) the refund task of an
 * overpaid order when the organizer policy says "refund" (S07). `donation` and `ask` leave the decision to a human.
 */
final readonly class OpenRefundForOverpayment
{
    public function __construct(public string $orderId) {}
}
