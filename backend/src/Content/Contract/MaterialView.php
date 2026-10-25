<?php

declare(strict_types=1);

namespace Eventor\Content\Contract;

/**
 * A downloadable material.
 */
final readonly class MaterialView
{
    public function __construct(
        public string $title,
        public string $url,
        public string $mediaType,
    ) {}
}
