<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AllocateRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $orderId,
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 500)]
        public string $reason,
        #[Assert\Uuid]
        public ?string $paymentRequestId = null,
    ) {}
}
