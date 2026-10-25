<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

final readonly class FormVersionSummaryView
{
    public function __construct(
        public string $id,
        public int $number,
        public \DateTimeImmutable $publishedAt,
        public ?string $schemaSha256,
    ) {}
}
