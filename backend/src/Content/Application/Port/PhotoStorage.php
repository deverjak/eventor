<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Port;

/**
 * Object storage of photo originals and thumbnails (MinIO / S3 under the `content/` prefix, a local directory in tests).
 */
interface PhotoStorage
{
    public function put(string $key, string $bytes): void;

    public function read(string $key): string;
}
