<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Payments\Contract\PaymentRequestPurpose;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Money;

/**
 * Pairrot confirmed a payment request; the instructions (IBAN, SPAYD) are readable through `FinanceQuery::paymentInstructions()`. Registration extends the hold when the instructions came late.
 *
 * P0. Planned subscribers: communication, registration.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.payment_request_issued', version: 1)]
final readonly class PaymentRequestIssued
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public PaymentRequestId $paymentRequestId,
        public string $variableSymbol,
        public Money $amount,
        public \DateTimeImmutable $dueAt,
        public PaymentRequestPurpose $purpose,
        public \DateTimeImmutable $occurredAt,
    ) {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('A payment request asks for a positive amount.');
        }
    }
}
