<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class IssueDeviceTokenRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $label = '',
        #[Assert\Uuid]
        public ?string $checkInListId = null,
        public ?\DateTimeImmutable $expiresAt = null,
    ) {}
}
