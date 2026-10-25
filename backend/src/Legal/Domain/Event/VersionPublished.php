<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Event;

use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a version became the current, immutable version of its document. Audited; translated into the
 * integration event `legal.document_version_published`.
 */
final readonly class VersionPublished implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public LegalDocumentId $documentId,
        public DocumentVersionId $versionId,
        public ?DocumentVersionId $previousVersionId,
        public DocumentLevel $level,
        public string $scopeId,
        public int $number,
        public string $sha256,
        public bool $requiresReconfirmation,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('document_version', $this->versionId->toString(), 'legal.version_published', $this->organizerId, after: [
            'documentId' => $this->documentId->toString(),
            'number' => $this->number,
            'sha256' => $this->sha256,
            'requiresReconfirmation' => $this->requiresReconfirmation,
        ]);
    }
}
