<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Query\View;

final readonly class PhotoAdminView
{
    public function __construct(
        public string $id,
        public ?string $description,
        public ?string $author,
        public ?string $eventPart,
        public int $position,
        public bool $hasThumbnail,
    ) {}
}
