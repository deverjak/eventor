<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * A published news post is not edited or published again (readers and e-mails already have it).
 */
#[AsProblem(code: 'news_post_already_published', status: 409)]
final class NewsPostAlreadyPublished extends DomainException
{
    public static function create(): self
    {
        return new self('The news post is already published.');
    }
}
