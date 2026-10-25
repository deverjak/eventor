<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Eventor\Payments\Contract\PaymentRequestPurpose;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;

final readonly class RequestPlan
{
    /**
     * @param list<PaymentRequestId> $cancel requests to cancel (none of them has allocations)
     */
    public function __construct(
        public array $cancel,
        public ?Money $issueAmount,
        public ?PaymentRequestPurpose $issuePurpose,
    ) {}

    public function isEmpty(): bool
    {
        return [] === $this->cancel && null === $this->issueAmount;
    }
}
