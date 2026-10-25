<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecordCashRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $amountMinor,
        #[Assert\Length(max: 500)]
        public ?string $note = null,
    ) {}
}
