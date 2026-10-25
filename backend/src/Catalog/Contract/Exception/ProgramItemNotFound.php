<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\ProgramItemId;

/**
 * No such program item.
 */
#[AsProblem(code: 'program_item_not_found', status: 404)]
final class ProgramItemNotFound extends DomainException
{
    public static function forId(ProgramItemId $id): self
    {
        return new self(\sprintf('Program item "%s" was not found.', $id->toString()));
    }
}
