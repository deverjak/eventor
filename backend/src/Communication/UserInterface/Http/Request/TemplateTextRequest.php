<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TemplateTextRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 300)]
        public string $subject,
        #[Assert\NotBlank]
        #[Assert\Length(max: 20000)]
        public string $body,
    ) {}
}
