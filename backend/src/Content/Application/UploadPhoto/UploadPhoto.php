<?php

declare(strict_types=1);

namespace Eventor\Content\Application\UploadPhoto;

final readonly class UploadPhoto
{
    public function __construct(
        public string $eventId,
        public string $collectionId,
        public string $bytes,
        public ?string $description,
        public ?string $author,
        public ?string $eventPart,
        public ?int $position,
    ) {}
}
