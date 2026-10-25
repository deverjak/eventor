<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\RecordManualPayment;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Finance books (positive) or removes (negative) a payment Pairrot does not know about, e.g. a transfer to another
 * account or a voucher: a `manual_correction` with a mandatory reason (chapter 05).
 */
final readonly class RecordManualPayment
{
    public function __construct(
        #[Assert\NotBlank]
        public string $orderId,
        #[Assert\NotEqualTo(0)]
        public int $amountMinor,
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 500)]
        public string $reason,
        #[Assert\NotBlank]
        #[Assert\Length(min: 16, max: 128)]
        public string $idempotencyKey,
    ) {}
}
