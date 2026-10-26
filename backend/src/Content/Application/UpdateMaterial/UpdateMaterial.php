<?php

declare(strict_types=1);

namespace Eventor\Content\Application\UpdateMaterial;

/**
 * Publish or unpublish a material, or (un)designate it for the public archive; null leaves that aspect unchanged.
 */
final readonly class UpdateMaterial
{
    public function __construct(
        public string $eventId,
        public string $materialId,
        public ?bool $published = null,
        public ?bool $inArchive = null,
    ) {}
}
