<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\DocumentVersionId;

final class DocumentVersionIdType extends AbstractIdType
{
    public const string NAME = 'document_version_id';

    protected function idClass(): string
    {
        return DocumentVersionId::class;
    }
}
