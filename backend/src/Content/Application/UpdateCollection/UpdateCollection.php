<?php

declare(strict_types=1);

namespace Eventor\Content\Application\UpdateCollection;

/**
 * Publish or unpublish a collection, or (un)designate it for the public archive; null leaves that aspect unchanged.
 */
final readonly class UpdateCollection
{
    public function __construct(
        public string $eventId,
        public string $collectionId,
        public ?bool $published = null,
        public ?bool $inArchive = null,
    ) {}
}
