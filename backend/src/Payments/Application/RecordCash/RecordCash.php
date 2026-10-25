<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\RecordCash;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Admin: cash taken for an order outside the check-in desk (PAY-07). Idempotent per `idempotencyKey`.
 */
final readonly class RecordCash
{
    public function __construct(
        #[Assert\NotBlank]
        public string $orderId,
        #[Assert\Positive]
        public int $amountMinor,
        #[Assert\NotBlank]
        #[Assert\Length(min: 16, max: 128)]
        public string $idempotencyKey,
        #[Assert\Length(max: 500)]
        public ?string $note = null,
    ) {}
}
