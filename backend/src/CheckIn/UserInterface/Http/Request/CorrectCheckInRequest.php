<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CorrectCheckInRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $reason,
        #[Assert\Uuid]
        public ?string $stationId = null,
        public ?\DateTimeImmutable $checkedInAt = null,
    ) {}
}
