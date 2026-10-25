<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\CorrectCashEntry;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * S11 / PAY-08: a cash entry was wrong. The original stays; a `cash_correction` with the negated amount compensates
 * it, and, when `replacementAmountMinor` is given, a new `cash` entry records the right amount. The reason is mandatory.
 */
final readonly class CorrectCashEntry
{
    public function __construct(
        #[Assert\NotBlank]
        public string $orderId,
        #[Assert\NotBlank]
        public string $ledgerEntryId,
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 500)]
        public string $reason,
        #[Assert\NotBlank]
        #[Assert\Length(min: 16, max: 128)]
        public string $idempotencyKey,
        #[Assert\Positive]
        public ?int $replacementAmountMinor = null,
    ) {}
}
