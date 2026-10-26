<?php

declare(strict_types=1);

namespace Eventor\Content\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddMaterialRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $title,
        #[Assert\NotBlank]
        #[Assert\Url(protocols: ['https'], requireTld: true)]
        #[Assert\Length(max: 2000)]
        public string $url,
        #[Assert\Choice(choices: ['results', 'video', 'document', 'partners', 'other'])]
        public string $type = 'other',
        #[Assert\PositiveOrZero]
        public int $position = 0,
    ) {}
}
