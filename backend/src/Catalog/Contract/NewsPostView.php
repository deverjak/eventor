<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\NewsPostId;

/**
 * A published news post.
 */
final readonly class NewsPostView
{
    public function __construct(
        public NewsPostId $id,
        public EventId $eventId,
        public string $title,
        public string $body,
        public \DateTimeImmutable $publishedAt,
    ) {}
}
