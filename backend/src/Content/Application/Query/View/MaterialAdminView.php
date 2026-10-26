<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Query\View;

final readonly class MaterialAdminView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public string $type,
        public int $position,
        public bool $published,
        public bool $inArchive,
    ) {}
}
