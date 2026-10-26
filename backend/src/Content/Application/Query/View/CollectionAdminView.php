<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Query\View;

/**
 * A collection as the organizer manages it: drafts included, the link-only token visible to the team.
 */
final readonly class CollectionAdminView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $visibility,
        public ?string $externalUrl,
        public ?string $linkToken,
        public bool $published,
        public bool $inArchive,
        public int $photoCount,
    ) {}
}
