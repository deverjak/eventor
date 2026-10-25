<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Program;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\NewsPostId;

final readonly class PublishNewsPost
{
    public function __construct(
        public EventId $eventId,
        public NewsPostId $newsPostId,
    ) {}
}
