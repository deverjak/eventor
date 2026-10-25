<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Step 1 of "Vytvoření platebního požadavku" (chapter 04, path 3): the local mirror exists (state PENDING) before
 * Pairrot is called. Idempotent per (order, attempt).
 */
final readonly class RegisterPaymentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $orderId,
        #[Assert\Positive]
        public int $attempt,
        #[Assert\Choice(choices: ['initial', 'retry', 'supplementary'])]
        public string $purpose,
        #[Assert\Positive]
        public int $amountMinor,
        #[Assert\NotBlank]
        #[Assert\Length(max: 60)]
        public string $message,
        #[Assert\NotBlank]
        public string $dueAt,
    ) {}
}
