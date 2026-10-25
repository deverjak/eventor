<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract\Event;

use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Symfony\Component\Uid\Uuid;

/**
 * An immutable version of a legal document was published (LEG-08). `scopeId` is the id of the organizer, event or activity the document belongs to, according to `level`.
 *
 * P0. Planned subscribers: legal, communication.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'legal.document_version_published', version: 1)]
#[PendingSubscribers('communication')]
final readonly class DocumentVersionPublished
{
    public function __construct(
        public OrganizerId $organizerId,
        public LegalDocumentId $documentId,
        public DocumentVersionId $versionId,
        public ?DocumentVersionId $previousVersionId,
        public DocumentLevel $level,
        public Uuid $scopeId,
        public bool $requiresReconfirmation,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
