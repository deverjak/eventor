<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Program;

use Eventor\Catalog\Contract\Exception\NewsPostNotFound;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\NewsPostId;

interface NewsPostRepository
{
    /**
     * @throws NewsPostNotFound
     */
    public function get(EventId $event, NewsPostId $id): NewsPost;

    public function add(NewsPost $post): void;
}
