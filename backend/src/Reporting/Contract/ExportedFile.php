<?php

declare(strict_types=1);

namespace Eventor\Reporting\Contract;

/**
 * A finished export.
 */
final readonly class ExportedFile
{
    public function __construct(
        public string $exportId,
        public string $url,
        public \DateTimeImmutable $expiresAt,
    ) {}
}
