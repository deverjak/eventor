<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VoidCheckInRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $reason,
    ) {}
}
