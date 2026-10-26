<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http\Request;

use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TemplatePreviewRequest
{
    public function __construct(
        public ?TemplateKey $key = null,
        public MessageLocale $locale = MessageLocale::Czech,
        #[Assert\Length(max: 300)]
        public ?string $subject = null,
        #[Assert\Length(max: 20000)]
        public ?string $body = null,
    ) {}
}
