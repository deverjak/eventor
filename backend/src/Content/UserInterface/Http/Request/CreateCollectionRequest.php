<?php

declare(strict_types=1);

namespace Eventor\Content\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCollectionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $title,
        /** `participants` (registered only, through self-service) is P1. */
        #[Assert\Choice(choices: ['public', 'link_only'])]
        public string $visibility = 'public',
        /** A photographer's gallery (POST-02); without it the collection is an internal gallery for uploads. */
        #[Assert\Url(protocols: ['https'], requireTld: true)]
        #[Assert\Length(max: 2000)]
        public ?string $externalUrl = null,
    ) {}
}
