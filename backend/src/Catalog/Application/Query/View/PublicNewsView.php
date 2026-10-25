<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

final readonly class PublicNewsView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        public int $priority,
        public \DateTimeImmutable $publishedAt,
    ) {}
}
