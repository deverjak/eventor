<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PaymentMethod;

use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * A zero total: nothing to pay, the seat is confirmed at once. Chosen by the system, never listed by the organizer.
 */
final readonly class FreeStrategy implements PaymentMethodStrategy
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::Free;
    }

    public function claimKind(): ClaimKind
    {
        return ClaimKind::Confirmed;
    }

    public function holdsSeat(): bool
    {
        return false;
    }

    public function paymentStatus(): string
    {
        return 'not_required';
    }

    public function accepts(bool $enabledForEvent, bool $zeroTotal): bool
    {
        return $zeroTotal;
    }
}
