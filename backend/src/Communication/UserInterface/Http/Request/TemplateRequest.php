<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http\Request;

use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TemplateRequest
{
    public function __construct(
        public TemplateKey $key,
        public MessageLocale $locale,
        #[Assert\NotBlank]
        #[Assert\Length(max: 300)]
        public string $subject,
        #[Assert\NotBlank]
        #[Assert\Length(max: 20000)]
        public string $body,
    ) {}
}
