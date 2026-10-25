<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Document;

use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;

/**
 * Persistence of documents and their versions. The tenant filter scopes every lookup to the current organizer, so a
 * document of another organizer is simply not found.
 */
interface LegalDocumentRepository
{
    public function find(LegalDocumentId $id): ?LegalDocument;

    public function add(LegalDocument $document): void;

    public function findVersion(DocumentVersionId $id): ?DocumentVersion;

    public function nextVersionNumber(LegalDocumentId $document): int;

    /**
     * The unpublished draft of the document, if any (at most one exists).
     */
    public function findDraft(LegalDocumentId $document): ?DocumentVersion;

    public function addVersion(DocumentVersion $version): void;

    /**
     * Drops a draft that is replaced by a newer upload. Published versions can never be removed.
     */
    public function removeDraft(DocumentVersion $draft): void;
}
