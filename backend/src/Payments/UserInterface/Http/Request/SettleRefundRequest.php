<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SettleRefundRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 32)]
        public string $method,
        #[Assert\Length(max: 190)]
        public ?string $reference = null,
        #[Assert\Length(max: 500)]
        public ?string $note = null,
    ) {}
}
