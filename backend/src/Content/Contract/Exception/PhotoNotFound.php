<?php

declare(strict_types=1);

namespace Eventor\Content\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\PhotoId;

#[AsProblem(code: 'photo_not_found', status: 404)]
final class PhotoNotFound extends DomainException
{
    public static function forId(PhotoId $id): self
    {
        return new self(\sprintf('Photo "%s" was not found.', $id->toString()));
    }
}
