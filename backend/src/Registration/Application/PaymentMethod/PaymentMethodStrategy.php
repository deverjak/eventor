<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PaymentMethod;

use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What differs between the ways of paying (chapter 04, path 2; ADR 002): bank transfer holds the seat until the
 * reservation expires, cash and free orders take the seat for good. Everything else in `PlaceOrder` is the same.
 */
#[AutoconfigureTag('registration.payment_method_strategy')]
interface PaymentMethodStrategy
{
    public function method(): PaymentMethod;

    /** Which counter the seats of a new order land in. */
    public function claimKind(): ClaimKind;

    /** Does a new participation wait for the money (`pending` with `hold_expires_at`)? */
    public function holdsSeat(): bool;

    /** The `payment.status` of the order response: `instructions_pending`, `cash_on_site` or `not_required`. */
    public function paymentStatus(): string;

    /** Can this method pay an order of the given total, with the methods the organizer enabled? */
    public function accepts(bool $enabledForEvent, bool $zeroTotal): bool;
}
