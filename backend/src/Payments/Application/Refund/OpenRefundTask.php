<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Refund;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * PAY-10: someone (EVENT_MANAGER, FINANCE, OWNER) asks for money to be returned to the participant.
 */
final readonly class OpenRefundTask
{
    public function __construct(
        #[Assert\NotBlank]
        public string $orderId,
        #[Assert\Positive]
        public int $amountMinor,
        #[Assert\Choice(choices: ['cancellation', 'overpayment', 'event_cancelled', 'late_payment'])]
        public string $reason,
        #[Assert\Length(max: 500)]
        public ?string $note = null,
    ) {}
}
