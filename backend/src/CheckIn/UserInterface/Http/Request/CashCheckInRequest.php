<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The body of "cash and check in" besides the participant reference.
 */
final readonly class CashCheckInRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $amountMinor,
        #[Assert\Length(max: 500)]
        public ?string $note = null,
        #[Assert\Uuid]
        public ?string $stationId = null,
    ) {}
}
