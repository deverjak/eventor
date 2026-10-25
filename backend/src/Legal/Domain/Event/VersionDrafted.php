<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a draft version with stored content exists. Audited.
 */
final readonly class VersionDrafted implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public LegalDocumentId $documentId,
        public DocumentVersionId $versionId,
        public int $number,
        public string $sha256,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('document_version', $this->versionId->toString(), 'legal.version_drafted', $this->organizerId, after: [
            'documentId' => $this->documentId->toString(),
            'number' => $this->number,
            'sha256' => $this->sha256,
        ]);
    }
}
