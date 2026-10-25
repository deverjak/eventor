<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The content or the placement of a legal document is not acceptable (empty text, not a PDF, unreachable external
 * link, a replacement that does not point to a higher level).
 */
#[AsProblem(code: 'invalid_legal_document', status: 422)]
final class InvalidDocument extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
