<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

final readonly class NewsPostAdminView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        public int $priority,
        public bool $broadcast,
        public ?string $authorAccountId,
        public bool $published,
        public ?\DateTimeImmutable $publishedAt,
        public \DateTimeImmutable $createdAt,
    ) {}
}
