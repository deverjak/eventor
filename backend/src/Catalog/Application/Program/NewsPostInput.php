<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of the news endpoints (PRG-04). `publish` on create publishes at once; `broadcast` also sends the post by e-mail.
 */
final readonly class NewsPostInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $title,
        #[Assert\NotBlank]
        #[Assert\Length(max: 20000)]
        public string $body,
        #[Assert\Range(min: 0, max: 10)]
        public int $priority = 0,
        public bool $broadcast = false,
        public bool $publish = false,
    ) {}
}
