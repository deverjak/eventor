<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * A cross-field rule of the catalogue is violated (the event ends before it starts, an age range is inverted, ...).
 * Per-field format errors are reported by request validation as `validation_failed`.
 */
#[AsProblem(code: 'invalid_catalog_data', status: 422)]
final class InvalidCatalogData extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
