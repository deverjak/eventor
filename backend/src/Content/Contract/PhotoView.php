<?php

declare(strict_types=1);

namespace Eventor\Content\Contract;

use Eventor\Shared\Domain\Id\PhotoId;

/**
 * A hosted photo with signed URLs (EXIF and GPS already stripped).
 */
final readonly class PhotoView
{
    public function __construct(
        public PhotoId $id,
        public string $url,
        public string $thumbnailUrl,
        public ?string $author,
        public int $position,
    ) {}
}
