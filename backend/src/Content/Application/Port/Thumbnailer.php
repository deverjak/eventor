<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Port;

use Eventor\Content\Contract\Exception\InvalidPhoto;

interface Thumbnailer
{
    /**
     * The MIME type of an accepted photo (JPEG, PNG, WebP).
     *
     * @throws InvalidPhoto when the bytes are not such an image
     */
    public function detect(string $bytes): string;

    /**
     * A JPEG fitted into a square of the given size.
     */
    public function thumbnail(string $bytes, int $maxSize): string;
}
