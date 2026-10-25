<?php

declare(strict_types=1);

namespace Eventor\Reporting\Contract\Exception;

use Eventor\Reporting\Contract\ExportType;
use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The actor may not export this kind of data (for example a check-in operator asking for finance).
 */
#[AsProblem(code: 'export_not_permitted', status: 403)]
final class ExportNotPermitted extends DomainException
{
    public static function forType(ExportType $type): self
    {
        return new self(\sprintf('Export "%s" is not permitted.', $type->value));
    }
}
