<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;

/**
 * What the planner needs to know about one payment request of an order.
 */
final readonly class RequestFacts
{
    public function __construct(
        public PaymentRequestId $id,
        public Money $amount,
        public Money $knownPaid,
        public bool $active,
    ) {}

    public function outstanding(): Money
    {
        $outstanding = $this->amount->subtract($this->knownPaid);

        return $outstanding->isPositive() ? $outstanding : Money::zero($this->amount->currency);
    }
}
