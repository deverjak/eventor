<?php

declare(strict_types=1);

namespace Eventor\Content\Contract;

use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * A collection. `externalUrl` is set for an external collection (a link, not hosted).
 */
final readonly class CollectionView
{
    public function __construct(
        public CollectionId $id,
        public EventId $eventId,
        public string $title,
        public CollectionVisibility $visibility,
        public ?string $externalUrl,
        public int $photoCount,
        public ?string $coverUrl,
    ) {}
}
