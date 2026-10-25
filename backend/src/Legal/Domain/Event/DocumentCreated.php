<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Event;

use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a legal document (without content yet) was created. Audited.
 */
final readonly class DocumentCreated implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public LegalDocumentId $documentId,
        public DocumentLevel $level,
        public string $scopeId,
        public string $title,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('legal_document', $this->documentId->toString(), 'legal.document_created', $this->organizerId, after: [
            'level' => $this->level->value,
            'scopeId' => $this->scopeId,
            'title' => $this->title,
        ]);
    }
}
