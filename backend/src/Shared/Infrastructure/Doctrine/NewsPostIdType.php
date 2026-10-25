<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\NewsPostId;

final class NewsPostIdType extends AbstractIdType
{
    public const string NAME = 'news_post_id';

    protected function idClass(): string
    {
        return NewsPostId::class;
    }
}
