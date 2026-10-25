<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Catalog\Contract\Exception\NewsPostNotFound;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * News posts of an event (PUB-04); Communication reads the content when a post is broadcast by e-mail.
 */
interface NewsReader
{
    /**
     * @throws NewsPostNotFound
     */
    public function post(OrganizerId $organizer, NewsPostId $post): NewsPostView;
}
