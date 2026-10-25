<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\LegalDocumentId;

/**
 * No such legal document or version.
 */
#[AsProblem(code: 'legal_document_not_found', status: 404)]
final class LegalDocumentNotFound extends DomainException
{
    public static function forId(LegalDocumentId $id): self
    {
        return new self(\sprintf('Legal document "%s" was not found.', $id->toString()));
    }
}
