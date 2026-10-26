<?php

declare(strict_types=1);

namespace Eventor\Content\Application\CreateCollection;

use Eventor\Content\Contract\CollectionVisibility;

final readonly class CreateCollection
{
    public function __construct(
        public string $eventId,
        public string $title,
        public CollectionVisibility $visibility,
        public ?string $externalUrl,
    ) {}
}
