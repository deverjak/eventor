<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Document;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Legal\Domain\Event\VersionDrafted;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * One version of a legal document. The bytes live in content-addressed storage under `storageKey`
 * (`sha256/<ab>/<hash>.<ext>`); the row keeps the hash so tampering is detectable by re-hashing (ADR 012). A draft can
 * be replaced; once `publishedAt` is set the row never changes (no setters, and a database trigger rejects it).
 */
#[ORM\Entity]
#[ORM\Table(name: 'document_version', schema: 'legal')]
#[ORM\UniqueConstraint(name: 'document_version_number_key', columns: ['document_id', 'number'])]
class DocumentVersion implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'boolean')]
    private bool $requiresReconfirmation = false;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $publishedBy = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'document_version_id')]
        private DocumentVersionId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'legal_document_id')]
        private LegalDocumentId $documentId,
        #[ORM\Column(type: 'integer')]
        private int $number,
        #[ORM\Column(type: 'string', length: 24, enumType: SourceKind::class)]
        private SourceKind $sourceKind,
        #[ORM\Column(type: 'string', length: 100)]
        private string $mime,
        #[ORM\Column(type: 'string', length: 2000, nullable: true)]
        private ?string $sourceUrl,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $fetchedAt,
        #[ORM\Column(name: 'content_sha256', type: 'string', length: 64, options: ['fixed' => true])]
        private string $sha256,
        #[ORM\Column(type: 'string', length: 255)]
        private string $storageKey,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function draft(
        DocumentVersionId $id,
        OrganizerId $organizerId,
        LegalDocumentId $documentId,
        int $number,
        SourceKind $sourceKind,
        string $mime,
        ?string $sourceUrl,
        ?\DateTimeImmutable $fetchedAt,
        string $sha256,
        string $storageKey,
        \DateTimeImmutable $now,
    ): self {
        $version = new self($id, $organizerId, $documentId, $number, $sourceKind, $mime, $sourceUrl, $fetchedAt, $sha256, $storageKey, $now);
        $version->record(new VersionDrafted($organizerId, $documentId, $id, $number, $sha256));

        return $version;
    }

    /**
     * @internal called by LegalDocument::publish() only
     */
    public function markPublished(bool $requiresReconfirmation, \DateTimeImmutable $now, ?string $publishedBy): void
    {
        $this->requiresReconfirmation = $requiresReconfirmation;
        $this->publishedAt = $now;
        $this->publishedBy = $publishedBy;
    }

    public function id(): DocumentVersionId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function documentId(): LegalDocumentId
    {
        return $this->documentId;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function sourceKind(): SourceKind
    {
        return $this->sourceKind;
    }

    public function mime(): string
    {
        return $this->mime;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function requiresReconfirmation(): bool
    {
        return $this->requiresReconfirmation;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }
}
