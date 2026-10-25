<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Unmatched;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 1 of "přiřadit" (PAY-06): validate the decision and audit it with the mandatory reason. The call to Pairrot
 * follows outside the transaction (UnmatchedAllocator). Never writes the ledger.
 */
final readonly class RequestAllocation
{
    public function __construct(
        #[Assert\NotBlank]
        public string $transactionId,
        #[Assert\NotBlank]
        public string $orderId,
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 500)]
        public string $reason,
        public ?string $paymentRequestId = null,
    ) {}
}
