<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PaymentMethod;

use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * Bank transfer: the seat is held (`held` counter, `pending` participation) until Payments reports the money or the
 * reservation expires. The payment request is created asynchronously from `registration.order_placed` (PAY-02).
 */
final readonly class BankTransferStrategy implements PaymentMethodStrategy
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::BankTransfer;
    }

    public function claimKind(): ClaimKind
    {
        return ClaimKind::Held;
    }

    public function holdsSeat(): bool
    {
        return true;
    }

    public function paymentStatus(): string
    {
        return 'instructions_pending';
    }

    public function accepts(bool $enabledForEvent, bool $zeroTotal): bool
    {
        return $enabledForEvent && !$zeroTotal;
    }
}
