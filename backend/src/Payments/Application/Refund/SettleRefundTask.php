<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Refund;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * PAY-11: finance returned the money outside the system and closes the task; a `refund_settled` entry is written.
 */
final readonly class SettleRefundTask
{
    public function __construct(
        #[Assert\NotBlank]
        public string $refundTaskId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 32)]
        public string $method,
        #[Assert\Length(max: 190)]
        public ?string $reference = null,
        #[Assert\Length(max: 500)]
        public ?string $note = null,
    ) {}
}
