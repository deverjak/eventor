<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Rendering;

final readonly class ResolvedTemplate
{
    public function __construct(
        public string $subject,
        public string $body,
        public bool $custom,
    ) {}
}
