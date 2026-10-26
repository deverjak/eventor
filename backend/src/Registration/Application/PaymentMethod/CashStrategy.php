<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PaymentMethod;

use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * Cash on site: the seat is taken at once (`confirmed`); the money is recorded later at check-in (CashDesk).
 */
final readonly class CashStrategy implements PaymentMethodStrategy
{
    public function method(): PaymentMethod
    {
        return PaymentMethod::Cash;
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
        return 'cash_on_site';
    }

    public function accepts(bool $enabledForEvent, bool $zeroTotal): bool
    {
        return $enabledForEvent && !$zeroTotal;
    }
}
