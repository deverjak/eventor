<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Refund;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CancelRefundTask
{
    public function __construct(
        #[Assert\NotBlank]
        public string $refundTaskId,
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 500)]
        public string $reason,
    ) {}
}
