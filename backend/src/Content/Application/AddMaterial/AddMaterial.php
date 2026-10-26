<?php

declare(strict_types=1);

namespace Eventor\Content\Application\AddMaterial;

use Eventor\Content\Contract\MaterialType;

final readonly class AddMaterial
{
    public function __construct(
        public string $eventId,
        public string $title,
        public string $url,
        public MaterialType $type,
        public int $position = 0,
    ) {}
}
