<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A body that is just a mandatory reason (cancel a refund task).
 */
final readonly class ReasonRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 500)]
        public string $reason,
    ) {}
}
