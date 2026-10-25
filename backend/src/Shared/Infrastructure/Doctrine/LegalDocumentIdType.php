<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\LegalDocumentId;

final class LegalDocumentIdType extends AbstractIdType
{
    public const string NAME = 'legal_document_id';

    protected function idClass(): string
    {
        return LegalDocumentId::class;
    }
}
