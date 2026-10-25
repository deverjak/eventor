<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Document;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Legal\Domain\Event\DocumentCreated;
use Eventor\Legal\Domain\Event\VersionPublished;
use Eventor\Legal\Domain\Exception\DocumentVersionAlreadyPublished;
use Eventor\Legal\Domain\Exception\InvalidDocument;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * A legal document of an organizer, an event or an activity (LEG-02). It owns its immutable versions
 * (`DocumentVersion`, referenced by id) and points to the current published one. A document of a lower level may
 * replace a document of a higher level (`replacesDocumentId`): the replaced one then stops applying below it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'legal_document', schema: 'legal')]
#[ORM\Index(name: 'legal_document_scope_idx', columns: ['organizer_id', 'level', 'scope_id'])]
class LegalDocument implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'document_version_id', nullable: true)]
    private ?DocumentVersionId $currentVersionId = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'legal_document_id')]
        private LegalDocumentId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'string', length: 16, enumType: DocumentLevel::class)]
        private DocumentLevel $level,
        #[ORM\Column(type: 'guid')]
        private string $scopeId,
        #[ORM\Column(type: 'string', length: 200)]
        private string $title,
        #[ORM\Column(type: 'legal_document_id', nullable: true)]
        private ?LegalDocumentId $replacesDocumentId,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        LegalDocumentId $id,
        OrganizerId $organizerId,
        DocumentLevel $level,
        string $scopeId,
        string $title,
        ?self $replaces,
        \DateTimeImmutable $now,
    ): self {
        if ('' === trim($title)) {
            throw InvalidDocument::because('A legal document needs a title.');
        }
        if (null !== $replaces && self::rank($replaces->level) >= self::rank($level)) {
            throw InvalidDocument::because('A document can only replace a document of a higher level (organizer > event > activity).');
        }
        if (DocumentLevel::Organizer === $level && $scopeId !== $organizerId->toString()) {
            throw InvalidDocument::because('An organizer-level document has the organizer as its scope.');
        }

        $document = new self($id, $organizerId, $level, $scopeId, trim($title), $replaces?->id(), $now);
        $document->record(new DocumentCreated($organizerId, $id, $level, $scopeId, $document->title));

        return $document;
    }

    /**
     * Makes a draft the current, immutable version. The `requiresReconfirmation` flag only makes sense when an
     * earlier version exists (LEG-08).
     */
    public function publish(DocumentVersion $version, bool $requiresReconfirmation, \DateTimeImmutable $now, ?string $publishedBy): void
    {
        if (!$version->documentId()->equals($this->id)) {
            throw new \LogicException('The version belongs to another document.');
        }
        if ($version->isPublished()) {
            throw new DocumentVersionAlreadyPublished(\sprintf('Version %d is already published.', $version->number()));
        }

        $previous = $this->currentVersionId;
        $version->markPublished($requiresReconfirmation && null !== $previous, $now, $publishedBy);
        $this->currentVersionId = $version->id();
        $this->record(new VersionPublished(
            $this->organizerId,
            $this->id,
            $version->id(),
            $previous,
            $this->level,
            $this->scopeId,
            $version->number(),
            $version->sha256(),
            $version->requiresReconfirmation(),
        ));
    }

    public function id(): LegalDocumentId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function level(): DocumentLevel
    {
        return $this->level;
    }

    public function scopeId(): string
    {
        return $this->scopeId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function replacesDocumentId(): ?LegalDocumentId
    {
        return $this->replacesDocumentId;
    }

    public function currentVersionId(): ?DocumentVersionId
    {
        return $this->currentVersionId;
    }

    private static function rank(DocumentLevel $level): int
    {
        return match ($level) {
            DocumentLevel::Organizer => 0,
            DocumentLevel::Event => 1,
            DocumentLevel::Activity => 2,
        };
    }
}
