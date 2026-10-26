<?php

declare(strict_types=1);

namespace Eventor\Content\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\MaterialId;

#[AsProblem(code: 'material_not_found', status: 404)]
final class MaterialNotFound extends DomainException
{
    public static function forId(MaterialId $id): self
    {
        return new self(\sprintf('Material "%s" was not found.', $id->toString()));
    }
}
