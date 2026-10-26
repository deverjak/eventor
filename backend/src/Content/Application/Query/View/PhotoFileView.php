<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Query\View;

/**
 * Where the bytes of a visible photo are, for the public image endpoint.
 */
final readonly class PhotoFileView
{
    public function __construct(
        public string $storageKey,
        public ?string $thumbnailKey,
        public string $contentType,
    ) {}
}
