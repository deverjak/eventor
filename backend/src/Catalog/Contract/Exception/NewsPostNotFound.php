<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\NewsPostId;

/**
 * No such news post.
 */
#[AsProblem(code: 'news_post_not_found', status: 404)]
final class NewsPostNotFound extends DomainException
{
    public static function forId(NewsPostId $id): self
    {
        return new self(\sprintf('News post "%s" was not found.', $id->toString()));
    }
}
