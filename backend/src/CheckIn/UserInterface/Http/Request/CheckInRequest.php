<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The body of a check-in besides the participant reference (`qr` | `code` | `participationId`, see the resolver).
 */
final readonly class CheckInRequest
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $stationId = null,
        public bool $acknowledgeWarning = false,
    ) {}
}
