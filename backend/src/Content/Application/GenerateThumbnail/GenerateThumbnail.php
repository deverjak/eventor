<?php

declare(strict_types=1);

namespace Eventor\Content\Application\GenerateThumbnail;

final readonly class GenerateThumbnail
{
    public function __construct(public string $photoId) {}
}
