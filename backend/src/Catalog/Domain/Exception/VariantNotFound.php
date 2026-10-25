<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * The variant does not exist, belongs to another event or to another organizer.
 */
#[AsProblem(code: 'variant_not_found', status: 404)]
final class VariantNotFound extends DomainException
{
    public static function forId(VariantId $id): self
    {
        return new self(\sprintf('Variant "%s" was not found.', $id->toString()));
    }
}
