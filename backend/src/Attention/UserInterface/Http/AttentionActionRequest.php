<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AttentionActionRequest
{
    public function __construct(
        #[Assert\Length(max: 1000)]
        public ?string $note = null,
    ) {}
}
