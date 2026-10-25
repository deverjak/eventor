<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OpenRefundRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $orderId,
        #[Assert\Positive]
        public int $amountMinor,
        #[Assert\Choice(choices: ['cancellation', 'overpayment', 'event_cancelled', 'late_payment'])]
        public string $reason,
        #[Assert\Length(max: 500)]
        public ?string $note = null,
    ) {}
}
